<?php

/*
 * Güvenlik denetimi bulguları için testler:
 *  1. Seeder'da varsayılan şifre
 *  2. Özel nitelikli verilere erişimin kayıt altına alınmaması
 *  3. Tahmin edilebilir barkodlara karşı deneme yanılma
 *  4. Süresi dolmayan giriş token'ları
 *  5. Vercel aracısı yüzünden ziyaretçi IP'sinin yanlış okunması
 *  6. Hasta sorgusu ile personel girişinin aynı istek sayacını paylaşması
 */

use App\Models\AccessLog;
use App\Models\Patient;
use App\Models\TestResult;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

function auditUser(string $role = 'laborant'): User
{
    return User::create([
        'name' => 'Denetim '.$role,
        'email' => $role.'@denetim.test',
        'role' => $role,
        'password' => Hash::make('sifre-1234'),
    ]);
}

function auditResult(string $identity = '10000000146', string $barcode = 'BARKOD-2026-001'): TestResult
{
    $patient = Patient::create(['identity_number' => $identity, 'full_name' => 'Ayşe Yılmaz']);

    return TestResult::create([
        'patient_id' => $patient->id,
        'barcode_number' => $barcode,
        'test_name' => 'Hemogram',
        'result_details' => 'Normal',
    ]);
}

function lookup(string $barcode, string $lastFour)
{
    return test()->postJson('/api/sonuc/'.$barcode, ['identity_last_four' => $lastFour]);
}

/* ---------------- 3. Barkod başına kilit ---------------- */

it('aynı barkoda 5 hatalı denemeden sonra doğru bilgiyle bile sonuç vermez', function () {
    auditResult(identity: '10000000146');

    foreach (range(1, 5) as $i) {
        lookup('BARKOD-2026-001', '9999')->assertStatus(404);
    }

    lookup('BARKOD-2026-001', '0146')
        ->assertStatus(429)
        ->assertJsonPath('locked', true);
});

it('barkod kilidi diğer barkodları etkilemez', function () {
    auditResult(identity: '10000000146', barcode: 'BARKOD-2026-001');
    auditResult(identity: '10000000214', barcode: 'BARKOD-2026-002');

    foreach (range(1, 5) as $i) {
        lookup('BARKOD-2026-001', '9999');
    }

    lookup('BARKOD-2026-002', '0214')->assertStatus(200);
});

it('kilit var olmayan barkodda da aynı davranır, barkodun varlığını ele vermez', function () {
    auditResult(barcode: 'BARKOD-2026-001');

    foreach (range(1, 5) as $i) {
        lookup('BARKOD-2026-001', '9999');
        lookup('YOK-BOYLE-BIR-BARKOD', '9999');
    }

    // 10 istekle IP başına dakikalık sınır doldu; barkod kilidini ayrıca
    // ölçebilmek için IP sınırının sıfırlanmasını bekliyoruz (kilit 1 saat)
    $this->travel(61)->seconds();

    $real = lookup('BARKOD-2026-001', '9999');
    $fake = lookup('YOK-BOYLE-BIR-BARKOD', '9999');

    expect($real->status())->toBe(429)->and($fake->status())->toBe(429)
        ->and($real->json())->toEqual($fake->json());
});

it('kilit bir saat sonra kalkar', function () {
    auditResult(identity: '10000000146');

    foreach (range(1, 5) as $i) {
        lookup('BARKOD-2026-001', '9999');
    }

    $this->travel(61)->minutes();

    lookup('BARKOD-2026-001', '0146')->assertStatus(200);
});

/* ---------------- 2. Erişim logları ---------------- */

it('başarılı, başarısız ve kilitli hasta sorgularını kaydeder', function () {
    auditResult(identity: '10000000146');

    lookup('BARKOD-2026-001', '0146');
    foreach (range(1, 5) as $i) {
        lookup('BARKOD-2026-001', '9999');
    }
    lookup('BARKOD-2026-001', '0146');

    $outcomes = AccessLog::where('event', 'hasta_sorgu')->orderBy('id')->pluck('outcome')->all();

    expect($outcomes)->toBe(['basarili', 'basarisiz', 'basarisiz', 'basarisiz', 'basarisiz', 'basarisiz', 'kilitli'])
        ->and(AccessLog::first()->subject)->toBe('BARKOD-2026-001')
        ->and(AccessLog::first()->user_id)->toBeNull();
});

it('erişim loguna kimlik numarası veya son 4 hane yazılmaz', function () {
    auditResult(identity: '10000000146');

    lookup('BARKOD-2026-001', '0146');
    lookup('BARKOD-2026-001', '5555');

    $everything = json_encode(DB::table('access_logs')->get());

    expect($everything)->not->toContain('10000000146')
        ->and($everything)->not->toContain('0146')
        ->and($everything)->not->toContain('5555');
});

it('giriş denemelerini kaydeder, yanlış yazılan e-posta adresini kaydetmez', function () {
    $user = auditUser();

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'sifre-1234'])->assertOk();
    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'yanlis'])->assertStatus(401);
    $this->postJson('/api/login', ['email' => 'hic-yok@baska.test', 'password' => 'yanlis'])->assertStatus(401);

    $logs = AccessLog::where('event', 'giris')->orderBy('id')->get();

    expect($logs->pluck('outcome')->all())->toBe(['basarili', 'basarisiz', 'basarisiz'])
        ->and($logs[0]->user_id)->toBe($user->id)
        ->and($logs[1]->user_id)->toBe($user->id)
        ->and($logs[2]->user_id)->toBeNull()
        ->and(json_encode(DB::table('access_logs')->get()))->not->toContain('hic-yok@baska.test');
});

it('tam kimlik numarasının görüntülendiği kayıt açılışını kimin yaptığıyla kaydeder', function () {
    $user = auditUser();
    $result = auditResult();
    Sanctum::actingAs($user);

    $this->getJson('/api/sonuclar/'.$result->id)->assertOk();

    $log = AccessLog::where('event', 'kayit_goruntuleme')->sole();

    expect($log->user_id)->toBe($user->id)
        ->and($log->subject)->toBe('BARKOD-2026-001');
});

it('erişim loglarını yalnızca yönetici görebilir', function () {
    auditResult();
    lookup('BARKOD-2026-001', '9999');

    Sanctum::actingAs(auditUser('laborant'));
    $this->getJson('/api/erisim-loglari')->assertStatus(403);

    Sanctum::actingAs(auditUser('admin'));
    $this->getJson('/api/erisim-loglari')->assertOk()->assertJsonCount(1);
});

it('bir yıldan eski erişim logları temizlenir, yenileri kalır', function () {
    AccessLog::create(['event' => 'giris', 'outcome' => 'basarili']);
    $old = AccessLog::create(['event' => 'giris', 'outcome' => 'basarili']);
    $old->forceFill(['created_at' => now()->subDays(366)])->save();

    $this->artisan('model:prune', ['--model' => [AccessLog::class]])->assertSuccessful();

    expect(AccessLog::count())->toBe(1)
        ->and(AccessLog::find($old->id))->toBeNull();
});

/* ---------------- 4. Token süresi ---------------- */

it('giriş tokenı 8 saat sonra geçersiz olur', function () {
    $user = auditUser();
    $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'sifre-1234'])->json('token');

    $this->travel(7)->hours();
    $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/sonuclar')->assertOk();

    $this->app['auth']->forgetGuards();
    $this->travel(61)->minutes();
    $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/sonuclar')->assertStatus(401);
});

/* ---------------- 1. Seeder şifreleri ---------------- */

function setSeedPasswords(?string $admin, ?string $lab): void
{
    foreach (['SEED_ADMIN_PASSWORD' => $admin, 'SEED_LAB_PASSWORD' => $lab] as $key => $value) {
        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        } else {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

afterEach(fn () => setSeedPasswords(null, null));

it('seeder şifre tanımlı değilse durur ve kullanıcı oluşturmaz', function () {
    setSeedPasswords(null, null);

    expect(fn () => $this->seed(DatabaseSeeder::class))->toThrow(RuntimeException::class)
        ->and(User::count())->toBe(0);
});

it('seeder kısa şifreyi kabul etmez', function () {
    setSeedPasswords('kisa', 'kartal-gol-gol-gol');

    expect(fn () => $this->seed(DatabaseSeeder::class))->toThrow(RuntimeException::class);
});

it('seeder güçlü şifrelerle kullanıcıları oluşturur', function () {
    setSeedPasswords('en-büyük-kartal', 'kartal-gol-gol-gol');

    $this->seed(DatabaseSeeder::class);

    expect(User::where('role', 'admin')->count())->toBe(1)
        ->and(User::where('role', 'laborant')->count())->toBe(1);
});

/* ---------------- 5. Ziyaretçi IP'si ---------------- */

it('Vercel dışında X-Forwarded-For başlığına güvenmez, sahte IP kaydedilmez', function () {
    auditResult();

    $this->withHeader('X-Forwarded-For', '203.0.113.99')
        ->postJson('/api/sonuc/BARKOD-2026-001', ['identity_last_four' => '9999']);

    expect(AccessLog::first()->ip_address)->not->toBe('203.0.113.99');
});

/* ---------------- 6. Ayrı istek sayaçları ---------------- */

it('hasta sorgusu sınırı dolsa da personel girişi etkilenmez', function () {
    $user = auditUser();
    auditResult();

    foreach (range(1, 10) as $i) {
        lookup('BARKOD-'.$i, '9999');
    }
    lookup('BARKOD-11', '9999')->assertStatus(429);

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'sifre-1234'])->assertOk();
});

it('giriş sınırı dolsa da hasta sorgusu etkilenmez', function () {
    $user = auditUser();
    auditResult(identity: '10000000146');

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'yanlis']);
    }
    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'sifre-1234'])->assertStatus(429);

    lookup('BARKOD-2026-001', '0146')->assertOk();
});
