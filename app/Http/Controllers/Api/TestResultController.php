<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Patient;
use App\Models\SystemLog;
use App\Models\TestResult;
use App\Rules\TurkishIdentityNumber;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class TestResultController extends Controller
{
    // Hasta sorgusunda barkod başına izin verilen hatalı deneme ve kilit süresi
    private const LOOKUP_MAX_FAILURES = 5;

    private const LOOKUP_LOCK_SECONDS = 3600;

    // Barkod alfabesi: karışabilecek 0/O ve 1/I harfleri yok (32 karakter)
    private const BARCODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const BARCODE_LENGTH = 8;

    /**
     * Hastanın kendi sonucunu sorgulaması (herkese açık).
     *
     * Barkod tek başına yeterli değildir; sorgulayanın hastanın T.C. Kimlik
     * Numarasının son 4 hanesini de bilmesi gerekir. Böylece barkod deneyerek
     * başkasının sonucuna ulaşılamaz. Yanıtta kimlik numarası yer almaz.
     */
    public function getResultByBarcode(Request $request, $barcode)
    {
        $validated = $request->validate([
            'identity_last_four' => ['required', 'digits:4'],
        ], [
            'identity_last_four.required' => 'T.C. Kimlik Numaranızın son 4 hanesini girmelisiniz.',
            'identity_last_four.digits' => 'T.C. Kimlik Numarasının son 4 hanesi 4 rakamdan oluşmalıdır.',
        ]);

        $barcode = mb_substr(mb_convert_case(trim((string) $barcode), MB_CASE_UPPER, 'UTF-8'), 0, 100);

        // IP başına sınır, çok sayıda IP kullanan bir saldırganı durdurmaz.
        // Bu yüzden barkod başına da sınır var: aynı barkoda 1 saatte 5 hatalı
        // denemeden sonra o barkod kilitlenir. Kilit, var olmayan barkodlar için
        // de aynı şekilde işler; aksi halde kilit mesajı barkodun var olduğunu
        // ele verirdi. Kilitliyken doğru bilgi girilse bile sonuç verilmez.
        $lockKey = 'hasta-sorgu-barkod:'.sha1($barcode);

        if (RateLimiter::tooManyAttempts($lockKey, self::LOOKUP_MAX_FAILURES)) {
            AccessLog::record($request, AccessLog::EVENT_PATIENT_LOOKUP, AccessLog::OUTCOME_LOCKED, null, $barcode);

            $minutes = max(1, (int) ceil(RateLimiter::availableIn($lockKey) / 60));

            return response()->json([
                'message' => "Bu barkod için çok fazla hatalı deneme yapıldı. Lütfen {$minutes} dakika sonra tekrar deneyin.",
                'locked' => true,
            ], 429);
        }

        $result = TestResult::with('patient')->where('barcode_number', $barcode)->first();

        $matches = $result
            && $result->patient
            && hash_equals(substr($result->patient->identity_number, -4), $validated['identity_last_four']);

        if (! $matches) {
            RateLimiter::hit($lockKey, self::LOOKUP_LOCK_SECONDS);
            AccessLog::record($request, AccessLog::EVENT_PATIENT_LOOKUP, AccessLog::OUTCOME_FAILURE, null, $barcode);

            // Barkod yanlış da olsa kimlik son hanesi yanlış da olsa aynı mesajı
            // döneriz; aksi halde hangi barkodların var olduğu tespit edilebilir.
            return response()->json([
                'message' => 'Girilen bilgilere ait bir sonuç bulunamadı.',
            ], 404);
        }

        AccessLog::record($request, AccessLog::EVENT_PATIENT_LOOKUP, AccessLog::OUTCOME_SUCCESS, null, $barcode);

        return response()->json([
            'patient_name' => $result->patient->full_name,
            'barcode_number' => $result->barcode_number,
            'test_name' => $result->test_name,
            'result_details' => $result->result_details,
            'created_at' => $result->created_at,
        ], 200);
    }

    // Tüm tahlil sonuçlarını arama ve sayfalama ile getirir
    public function getAllResults(Request $request)
    {
        $query = TestResult::with('patient');

        $searchTerm = trim((string) $request->query('search', ''));

        if ($searchTerm !== '') {
            // Arama koşullarını tek parantez içinde grupluyoruz; aksi halde
            // ileride eklenecek filtreler "veya" yüzünden devre dışı kalır.
            $like = '%'.mb_strtolower($searchTerm, 'UTF-8').'%';

            $query->where(function ($q) use ($like) {
                // LOWER(...) LIKE kullanımı PostgreSQL, MySQL ve SQLite'ta aynı çalışır
                $q->whereRaw('LOWER(barcode_number) LIKE ?', [$like])
                    ->orWhereHas('patient', function ($p) use ($like) {
                        $p->whereRaw('LOWER(full_name) LIKE ?', [$like]);
                    });
            });
        }

        $results = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json($results, 200);
    }

    // Yeni hasta ve tahlil sonucunu veritabanına ekler
    public function store(Request $request)
    {
        $validated = $request->validate([
            'identity_number' => ['required', new TurkishIdentityNumber],
            'full_name' => ['required', 'string', 'min:3', 'max:255', "regex:/^[\p{L}\s.'-]+$/u"],
            'test_name' => ['required', 'string', 'max:255'],
            'result_details' => ['required', 'string', 'max:5000'],
        ], [
            'full_name.regex' => 'Hasta adı yalnızca harf, boşluk ve - . \' işaretlerini içerebilir.',
        ]);

        // Barkod kullanıcıdan alınmaz, tahmin edilemeyecek şekilde üretilir
        $formattedBarcode = $this->generateBarcode();

        // Kullanıcı nasıl yazarsa yazsın, her kelimenin ilk harfini büyük yap (Türkçe uyumlu)
        $formattedFullName = mb_convert_case($validated['full_name'], MB_CASE_TITLE, 'UTF-8');

        $patient = Patient::firstOrCreate(
            ['identity_number' => $validated['identity_number']],
            ['full_name' => $formattedFullName]
        );

        $result = new TestResult;
        $result->patient_id = $patient->id;
        $result->barcode_number = $formattedBarcode;
        $result->test_name = $validated['test_name'];
        $result->result_details = $validated['result_details'];
        $result->save();

        SystemLog::create([
            'user_id' => Auth::id(),
            'action' => 'Ekleme',
            'description' => $formattedBarcode.' barkodlu hastanın ('.$formattedFullName.') tahlil sonucu eklendi.',
        ]);

        return response()->json([
            'message' => 'Kayıt başarıyla eklendi!',
            'barcode_number' => $formattedBarcode,
        ], 201);
    }

    /**
     * Tahmin edilemeyen benzersiz barkod üretir (ör. LP-7K2QX9M4).
     *
     * random_int kriptografik olarak güvenli rastgelelik kullanır. Çakışma
     * kontrolü çöp kutusundaki kayıtları da kapsar, çünkü veritabanındaki
     * benzersizlik kısıtı silinmiş kayıtlar için de geçerlidir.
     */
    private function generateBarcode(): string
    {
        $alphabetLength = strlen(self::BARCODE_ALPHABET);

        do {
            $code = 'LP-';

            for ($i = 0; $i < self::BARCODE_LENGTH; $i++) {
                $code .= self::BARCODE_ALPHABET[random_int(0, $alphabetLength - 1)];
            }
        } while (TestResult::withTrashed()->where('barcode_number', $code)->exists());

        return $code;
    }

    // Tek bir sonucu ID'ye göre getirir (düzenleme formu ve yazdırma belgesi için)
    public function show(Request $request, $id)
    {
        $result = TestResult::with('patient')->find($id);

        if (! $result) {
            return response()->json(['message' => 'Kayıt bulunamadı.'], 404);
        }

        // Kimlik numarası varsayılan olarak gizlidir; resmî belgede gerektiği
        // için yalnızca bu yetkili uç noktada görünür kılıyoruz. Tam kimlik
        // numarasının kimin tarafından görüntülendiği kayıt altına alınır.
        $result->patient?->makeVisible('identity_number');

        AccessLog::record($request, AccessLog::EVENT_RECORD_VIEW, AccessLog::OUTCOME_SUCCESS, $request->user()?->id, $result->barcode_number);

        return response()->json($result, 200);
    }

    // Tahlil sonucunu günceller
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'test_name' => ['required', 'string', 'max:255'],
            'result_details' => ['required', 'string', 'max:5000'],
        ]);

        $result = TestResult::find($id);

        if (! $result) {
            return response()->json(['message' => 'Kayıt bulunamadı.'], 404);
        }

        $result->test_name = $validated['test_name'];
        $result->result_details = $validated['result_details'];
        $result->save();

        SystemLog::create([
            'user_id' => Auth::id(),
            'action' => 'Güncelleme',
            'description' => $result->barcode_number.' barkodlu tahlil sonucu güncellendi.',
        ]);

        return response()->json(['message' => 'Kayıt başarıyla güncellendi!']);
    }

    // Tahlil sonucunu çöp kutusuna taşır (yalnızca yönetici)
    public function destroy($id)
    {
        $result = TestResult::find($id);

        if (! $result) {
            return response()->json(['message' => 'Kayıt bulunamadı.'], 404);
        }

        $barcode = $result->barcode_number;
        $result->delete();

        SystemLog::create([
            'user_id' => Auth::id(),
            'action' => 'Silme',
            'description' => $barcode.' barkodlu tahlil sonucu çöp kutusuna taşındı.',
        ]);

        return response()->json(['message' => 'Kayıt çöp kutusuna taşındı!']);
    }

    // Sistem loglarını kullanıcı (laborant) bilgisiyle beraber getirir (yalnızca yönetici)
    public function getLogs()
    {
        $logs = SystemLog::with('user:id,name')->orderBy('created_at', 'desc')->limit(200)->get();

        return response()->json($logs, 200);
    }

    // Özel nitelikli verilere erişim kayıtları (yalnızca yönetici)
    public function getAccessLogs()
    {
        $logs = AccessLog::with('user:id,name')->orderBy('created_at', 'desc')->orderBy('id', 'desc')->limit(200)->get();

        return response()->json($logs, 200);
    }

    // Sadece çöp kutusundaki (soft delete) kayıtları getirir
    public function getTrashedResults()
    {
        $trashed = TestResult::onlyTrashed()->with('patient')->orderBy('deleted_at', 'desc')->get();

        return response()->json($trashed, 200);
    }

    // Silinmiş bir kaydı geri yükler
    public function restore($id)
    {
        $result = TestResult::onlyTrashed()->find($id);

        if (! $result) {
            return response()->json(['message' => 'Kayıt bulunamadı.'], 404);
        }

        $result->restore();

        SystemLog::create([
            'user_id' => Auth::id(),
            'action' => 'Geri Yükleme',
            'description' => $result->barcode_number.' barkodlu tahlil sonucu çöp kutusundan geri yüklendi.',
        ]);

        return response()->json(['message' => 'Kayıt başarıyla geri yüklendi!']);
    }

    // Dashboard için özet istatistikleri getirir
    public function getStatistics()
    {
        return response()->json([
            'total_patients' => Patient::count(),
            'total_tests' => TestResult::count(),
            'today_tests' => TestResult::whereDate('created_at', Carbon::today())->count(),
            'trashed_tests' => TestResult::onlyTrashed()->count(),
        ], 200);
    }
}
