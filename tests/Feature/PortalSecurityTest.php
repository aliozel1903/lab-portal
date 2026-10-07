<?php

use App\Models\Patient;
use App\Models\SystemLog;
use App\Models\TestResult;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

function makeUser(string $role = 'laborant'): User
{
    return User::create([
        'name' => 'Test '.$role,
        'email' => $role.'@lab.test',
        'role' => $role,
        'password' => Hash::make('sifre-1234'),
    ]);
}

function makeResult(string $identityNumber = '10000000146', string $barcode = 'BARKOD456'): TestResult
{
    $patient = Patient::create([
        'identity_number' => $identityNumber,
        'full_name' => 'Ayşe Yılmaz',
    ]);

    return TestResult::create([
        'patient_id' => $patient->id,
        'barcode_number' => $barcode,
        'test_name' => 'Kan Tahlili',
        'result_details' => 'Tüm değerler normal aralıkta.',
    ]);
}

/* ---------------- Herkese açık sorgulama ---------------- */

it('kimlik son 4 hanesi olmadan sonuç vermez', function () {
    makeResult();

    $this->postJson('/api/sonuc/BARKOD456')
        ->assertStatus(422);
});

it('kimlik son 4 hanesi yanlışsa sonuç vermez', function () {
    makeResult(identityNumber: '10000000146');

    $this->postJson('/api/sonuc/BARKOD456', ['identity_last_four' => '9999'])
        ->assertStatus(404);
});

it('doğru bilgilerle sonucu döner ama kimlik numarasını sızdırmaz', function () {
    makeResult(identityNumber: '10000000146');

    $response = $this->postJson('/api/sonuc/BARKOD456', ['identity_last_four' => '0146'])
        ->assertStatus(200)
        ->assertJsonPath('patient_name', 'Ayşe Yılmaz')
        ->assertJsonPath('test_name', 'Kan Tahlili');

    expect($response->json())->not->toHaveKey('patient')
        ->and(json_encode($response->json()))->not->toContain('10000000146');
});

/* ---------------- Kayıt ekleme doğrulaması ---------------- */

it('geçersiz kimlik numarasıyla kayıt eklenemez', function (string $identity) {
    Sanctum::actingAs(makeUser());

    $this->postJson('/api/sonuclar', [
        'identity_number' => $identity,
        'full_name' => 'Mehmet Demir',
        'barcode_number' => 'BARKOD999',
        'test_name' => 'Kan Tahlili',
        'result_details' => 'Detaylar',
    ])->assertStatus(422)->assertJsonValidationErrors('identity_number');

    expect(TestResult::count())->toBe(0);
})->with(['ahmet', '12345678901', '123', '0123456789']);

it('geçerli kimlik numarasıyla kayıt eklenir ve barkod sistem tarafından üretilir', function () {
    Sanctum::actingAs(makeUser());

    $response = $this->postJson('/api/sonuclar', [
        'identity_number' => '10000000146',
        'full_name' => 'mehmet demir',
        'test_name' => 'Kan Tahlili',
        'result_details' => 'Detaylar',
    ])->assertStatus(201);

    $result = TestResult::with('patient')->first();

    expect($result->barcode_number)->toMatch('/^LP-[A-HJ-NP-Z2-9]{8}$/')
        ->and($response->json('barcode_number'))->toBe($result->barcode_number)
        ->and($result->patient->full_name)->toBe('Mehmet Demir');
});

it('hasta adında HTML etiketi kabul etmez', function () {
    Sanctum::actingAs(makeUser());

    $this->postJson('/api/sonuclar', [
        'identity_number' => '10000000146',
        'full_name' => '<img src=x onerror=alert(1)>',
        'barcode_number' => 'BARKOD999',
        'test_name' => 'Kan Tahlili',
        'result_details' => 'Detaylar',
    ])->assertStatus(422)->assertJsonValidationErrors('full_name');
});

it('kullanıcının gönderdiği barkod yok sayılır, her kayda farklı barkod üretilir', function () {
    Sanctum::actingAs(makeUser());

    foreach (['10000000146', '10000000146'] as $identity) {
        $this->postJson('/api/sonuclar', [
            'identity_number' => $identity,
            'full_name' => 'Mehmet Demir',
            'barcode_number' => 'BARKOD-2026-001',
            'test_name' => 'Kan Tahlili',
            'result_details' => 'Detaylar',
        ])->assertStatus(201);
    }

    $barcodes = TestResult::pluck('barcode_number');

    expect($barcodes)->toHaveCount(2)
        ->and($barcodes->unique())->toHaveCount(2)
        ->and($barcodes)->not->toContain('BARKOD-2026-001');
});

/* ---------------- Yetkilendirme ---------------- */

it('giriş yapmamış kullanıcı kayıtları listeleyemez', function () {
    $this->getJson('/api/sonuclar')->assertStatus(401);
});

it('laborant kayıt silemez', function () {
    Sanctum::actingAs(makeUser('laborant'));
    $result = makeResult();

    $this->deleteJson('/api/sonuclar/'.$result->id)->assertStatus(403);

    expect(TestResult::count())->toBe(1);
});

it('laborant sistem loglarını göremez', function () {
    Sanctum::actingAs(makeUser('laborant'));

    $this->getJson('/api/loglar')->assertStatus(403);
});

it('yönetici kaydı çöp kutusuna taşıyabilir', function () {
    Sanctum::actingAs(makeUser('admin'));
    $result = makeResult();

    $this->deleteJson('/api/sonuclar/'.$result->id)->assertStatus(200);

    expect(TestResult::count())->toBe(0)
        ->and(TestResult::onlyTrashed()->count())->toBe(1)
        ->and(SystemLog::where('action', 'Silme')->count())->toBe(1);
});

/* ---------------- Arama ---------------- */

it('hasta adına ve barkoda göre harf duyarsız arama yapar', function () {
    Sanctum::actingAs(makeUser());
    makeResult(identityNumber: '10000000146', barcode: 'BARKOD456');

    expect($this->getJson('/api/sonuclar?search=ayşe')->json('total'))->toBe(1)
        ->and($this->getJson('/api/sonuclar?search=barkod')->json('total'))->toBe(1)
        ->and($this->getJson('/api/sonuclar?search=bulunmayan')->json('total'))->toBe(0);
});

/* ---------------- Oturum ---------------- */

it('çıkış yapıldığında token sunucuda geçersiz olur', function () {
    $user = makeUser();

    $token = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'sifre-1234',
    ])->assertStatus(200)->json('token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/logout')->assertStatus(200);

    // Token veritabanından silinmiş olmalı
    expect($user->fresh()->tokens()->count())->toBe(0);

    // Aynı test içinde guard'ın önbelleğe aldığı kullanıcıyı temizliyoruz,
    // aksi halde istek token'a hiç bakmadan başarılı olur.
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/sonuclar')->assertStatus(401);
});

/* ---------------- Demo modu ---------------- */

it('demo modunda gerçek olabilecek kimlik numarasıyla kayıt eklenemez', function () {
    config(['app.demo_mode' => true]);
    Sanctum::actingAs(makeUser());

    $this->postJson('/api/sonuclar', [
        'identity_number' => '10000000146',
        'full_name' => 'Mehmet Demir',
        'barcode_number' => 'BARKOD999',
        'test_name' => 'Kan Tahlili',
        'result_details' => 'Detaylar',
    ])->assertStatus(422)->assertJsonValidationErrors('identity_number');

    expect(TestResult::count())->toBe(0);
});

it('demo modunda test kimlik numarasıyla kayıt eklenir', function () {
    config(['app.demo_mode' => true]);
    Sanctum::actingAs(makeUser());

    $this->postJson('/api/sonuclar', [
        'identity_number' => '10000000147',
        'full_name' => 'Mehmet Demir',
        'barcode_number' => 'BARKOD999',
        'test_name' => 'Kan Tahlili',
        'result_details' => 'Detaylar',
    ])->assertStatus(201);
});

it('demo modunda arayüzde uyarı şeridi görünür', function () {
    config(['app.demo_mode' => true]);

    $this->get('/')->assertOk()->assertSee('Bu bir demo sistemidir.');
});

it('demo modu kapalıyken uyarı şeridi görünmez', function () {
    config(['app.demo_mode' => false]);

    $this->get('/')->assertOk()->assertDontSee('Bu bir demo sistemidir.');
});

it('demo modunda yazdırma sayfası test belgesi olarak işaretlenir', function () {
    config(['app.demo_mode' => true]);

    $this->get('/yazdir/1')->assertOk()
        ->assertSee('TEST BELGESİ')
        ->assertDontSee('Elektronik İmzalıdır');
});
