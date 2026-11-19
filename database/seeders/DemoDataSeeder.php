<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\SystemLog;
use App\Models\TestResult;
use App\Models\User;
use App\Rules\TurkishIdentityNumber;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Herkese açık demo için kurgusal hasta ve tahlil verileri.
 *
 * Yalnızca DEMO_MODE=true iken çalışır. Kimlik numaraları demo kuralına
 * göre üretilmiştir (11. hane doğru değerin bir fazlası), bu yüzden hiçbiri
 * gerçek bir kişiye ait olamaz. Önce başlangıç kullanıcılarını oluşturur.
 *
 * Kullanım: php artisan db:seed --class=DemoDataSeeder
 * Tekrar çalıştırılırsa var olan kayıtları çoğaltmaz.
 */
class DemoDataSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string}> */
    private const RECORDS = [
        // [barkod, ad, kimlik no, tahlil, sonuç]
        ['BARKOD-2026-001', 'Ayşe Yılmaz', '12345678951', 'Hemogram', "Hemoglobin: 13.2 g/dL\nLökosit: 7.400 /µL\nTrombosit: 254.000 /µL\nTüm değerler referans aralığında."],
        ['BARKOD-2026-002', 'Mehmet Demir', '23159047147', 'Açlık Kan Şekeri', "Glukoz: 98 mg/dL\nReferans: 70-100 mg/dL\nSonuç normal sınırlarda."],
        ['BARKOD-2026-003', 'Zeynep Kaya', '38204619521', 'Tiroid Paneli', "TSH: 2.1 mIU/L\nT4: 1.3 ng/dL\nT3: 3.1 pg/mL\nTiroid fonksiyonları normal."],
        ['BARKOD-2026-004', 'Ali Çelik', '41738250641', 'Lipid Profili', "Total Kolesterol: 215 mg/dL (yüksek)\nLDL: 141 mg/dL (yüksek)\nHDL: 48 mg/dL\nTrigliserit: 165 mg/dL\nDiyet önerilir."],
        ['BARKOD-2026-005', 'Fatma Şahin', '50926371419', 'Vitamin D', "25-OH Vitamin D: 18 ng/mL\nReferans: 30-100 ng/mL\nEksiklik saptandı, takviye önerilir."],
        ['BARKOD-2026-006', 'Hüseyin Arslan', '64081592343', 'Karaciğer Fonksiyon', "ALT: 28 U/L\nAST: 24 U/L\nGGT: 31 U/L\nDeğerler normal."],
        ['BARKOD-2026-007', 'Elif Doğan', '72519406803', 'Tam İdrar Tahlili', "Protein: Negatif\nGlukoz: Negatif\nLökosit: 2-3 /HPF\nBulgular normal."],
    ];

    // Panelde çöp kutusu örneği olsun diye bu kayıt silinmiş olarak eklenir
    private const TRASHED_BARCODE = 'BARKOD-2026-007';

    public function run(): void
    {
        if (! config('app.demo_mode')) {
            throw new RuntimeException('Demo verileri yalnızca DEMO_MODE=true iken yüklenebilir.');
        }

        $this->call(DatabaseSeeder::class);

        $admin = User::where('role', 'admin')->firstOrFail();
        $laborant = User::where('role', 'laborant')->firstOrFail();
        $identityRule = new TurkishIdentityNumber(demoMode: true);

        foreach (self::RECORDS as $i => [$barcode, $name, $identity, $testName, $details]) {
            $identityRule->validate('identity_number', $identity, function (string $message) use ($identity) {
                throw new RuntimeException("Demo kimlik numarası geçersiz ({$identity}): {$message}");
            });

            $createdAt = now()->subDays(count(self::RECORDS) - $i)->setTime(9 + $i, 15);
            $user = $i % 2 === 0 ? $laborant : $admin;

            $patient = Patient::firstOrCreate(['identity_number' => $identity], ['full_name' => $name]);

            $result = TestResult::withTrashed()->firstOrNew(['barcode_number' => $barcode]);

            if ($result->exists) {
                continue;
            }

            $result->fill([
                'patient_id' => $patient->id,
                'test_name' => $testName,
                'result_details' => $details,
            ]);
            $result->created_at = $result->updated_at = $createdAt;
            $result->save();

            $this->log($user, SystemLog::ACTION_CREATE, $barcode.' barkodlu hastanın ('.Patient::maskName($name).') tahlil sonucu eklendi.', $createdAt);

            if ($barcode === self::TRASHED_BARCODE) {
                $deletedAt = $createdAt->copy()->addHours(2);
                $result->forceFill(['deleted_at' => $deletedAt])->save();
                $this->log($admin, SystemLog::ACTION_DELETE, $barcode.' barkodlu tahlil sonucu çöp kutusuna taşındı.', $deletedAt);
            }
        }
    }

    private function log(User $user, string $action, string $description, $at): void
    {
        $log = new SystemLog(['user_id' => $user->id, 'action' => $action, 'description' => $description]);
        $log->created_at = $log->updated_at = $at;
        $log->save();
    }
}
