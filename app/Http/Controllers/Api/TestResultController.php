<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\SystemLog;
use App\Models\TestResult;
use App\Rules\TurkishIdentityNumber;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TestResultController extends Controller
{
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

        $result = TestResult::with('patient')
            ->where('barcode_number', mb_convert_case($barcode, MB_CASE_UPPER, 'UTF-8'))
            ->first();

        // Barkod yanlış da olsa kimlik son hanesi yanlış da olsa aynı mesajı
        // döneriz; aksi halde hangi barkodların var olduğu tespit edilebilir.
        $notFound = response()->json([
            'message' => 'Girilen bilgilere ait bir sonuç bulunamadı.',
        ], 404);

        if (! $result || ! $result->patient) {
            return $notFound;
        }

        if (! hash_equals(substr($result->patient->identity_number, -4), $validated['identity_last_four'])) {
            return $notFound;
        }

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
            'barcode_number' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9-]+$/'],
            'test_name' => ['required', 'string', 'max:255'],
            'result_details' => ['required', 'string', 'max:5000'],
        ], [
            'full_name.regex' => 'Hasta adı yalnızca harf, boşluk ve - . \' işaretlerini içerebilir.',
            'barcode_number.regex' => 'Barkod numarası yalnızca harf, rakam ve tire (-) içerebilir.',
        ]);

        $formattedBarcode = mb_convert_case($validated['barcode_number'], MB_CASE_UPPER, 'UTF-8');

        // Barkod, çöp kutusundaki bir kayda ait olabilir. Veritabanındaki
        // benzersizlik kısıtı silinmiş kayıtları da kapsadığı için kullanıcıya
        // teknik hata yerine ne yapması gerektiğini anlatıyoruz.
        $existing = TestResult::withTrashed()->where('barcode_number', $formattedBarcode)->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'barcode_number' => $existing->trashed()
                    ? 'Bu barkod çöp kutusundaki bir kayda ait. Kaydı geri yükleyin veya farklı bir barkod girin.'
                    : 'Bu barkod numarası zaten kullanılıyor.',
            ]);
        }

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

        return response()->json(['message' => 'Kayıt başarıyla eklendi!'], 201);
    }

    // Tek bir sonucu ID'ye göre getirir (düzenleme formu ve yazdırma belgesi için)
    public function show($id)
    {
        $result = TestResult::with('patient')->find($id);

        if (! $result) {
            return response()->json(['message' => 'Kayıt bulunamadı.'], 404);
        }

        // Kimlik numarası varsayılan olarak gizlidir; resmî belgede gerektiği
        // için yalnızca bu yetkili uç noktada görünür kılıyoruz.
        $result->patient?->makeVisible('identity_number');

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
