<?php

/*
 * KVKK: hasta adlarının maskeli gösterimi
 *  - Listeler ve hasta sorgusu yalnızca maskeli adı döner
 *  - Tam ad yalnızca personel tarafından, tek tek ve loglanarak açılır
 *  - Loglarda hasta adı açık yazılmaz
 *  - Çöp kutusu yalnızca yöneticiye açıktır
 */

use App\Models\Patient;
use App\Models\SystemLog;
use App\Models\TestResult;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

function maskUser(string $role = 'laborant'): User
{
    return User::create([
        'name' => 'Maske '.$role,
        'email' => $role.'@maske.test',
        'role' => $role,
        'password' => Hash::make('sifre-1234'),
    ]);
}

function maskResult(string $name = 'Ayşe Yılmaz', string $barcode = 'LP-TEST0001', string $identity = '10000000146'): TestResult
{
    $patient = Patient::create(['identity_number' => $identity, 'full_name' => $name]);

    return TestResult::create([
        'patient_id' => $patient->id,
        'barcode_number' => $barcode,
        'test_name' => 'Hemogram',
        'result_details' => 'Normal',
    ]);
}

/* ---------------- Liste ve arama ---------------- */

it('liste yalnızca maskeli adı içerir, tam ad ve kimlik numarası yanıtta yoktur', function () {
    Sanctum::actingAs(maskUser());
    maskResult();

    $response = $this->getJson('/api/sonuclar')
        ->assertOk()
        ->assertJsonPath('data.0.patient.masked_name', 'A*** Y***');

    expect($response->json('data.0.patient'))->not->toHaveKey('full_name')
        ->and($response->content())->not->toContain('Ayşe')
        ->and($response->content())->not->toContain('Yılmaz')
        ->and($response->content())->not->toContain('10000000146');
});

it('tam adla arama sunucuda çalışır ama sonuç maskeli döner', function () {
    Sanctum::actingAs(maskUser());
    maskResult('Ayşe Yılmaz', 'LP-TEST0001', '10000000146');
    maskResult('Mehmet Demir', 'LP-TEST0002', '10000000214');

    $response = $this->getJson('/api/sonuclar?search='.urlencode('ayşe yılmaz'))->assertOk();

    expect($response->json('total'))->toBe(1)
        ->and($response->json('data.0.barcode_number'))->toBe('LP-TEST0001')
        ->and($response->json('data.0.patient.masked_name'))->toBe('A*** Y***')
        ->and($response->content())->not->toContain('Yılmaz');
});

it('panel sayfasının kaynağında hasta adı bulunmaz', function () {
    maskResult();

    $this->get('/')->assertOk()->assertDontSee('Ayşe')->assertDontSee('Yılmaz');
});

/* ---------------- Tam adı açma ---------------- */

it('laborant ve yönetici tam adı açabilir, her açılış adsız olarak loglanır', function (string $role) {
    $user = maskUser($role);
    Sanctum::actingAs($user);
    $result = maskResult();

    $this->postJson('/api/sonuclar/'.$result->id.'/ad-goster')
        ->assertOk()
        ->assertExactJson(['full_name' => 'Ayşe Yılmaz']);

    $log = SystemLog::sole();

    expect($log->action)->toBe('Görüntüleme')
        ->and($log->user_id)->toBe($user->id)
        ->and($log->description)->toBe('LP-TEST0001 barkodlu hastanın adı görüntülendi.')
        ->and($log->description)->not->toContain('Ayşe');
})->with(['laborant', 'admin']);

it('personel olmayan kullanıcı tam adı açamaz ve log yazılmaz', function () {
    Sanctum::actingAs(maskUser('misafir'));
    $result = maskResult();

    $this->postJson('/api/sonuclar/'.$result->id.'/ad-goster')
        ->assertStatus(403)
        ->assertJsonMissing(['full_name' => 'Ayşe Yılmaz']);

    expect(SystemLog::count())->toBe(0);
});

it('giriş yapmamış kullanıcı tam adı açamaz', function () {
    $result = maskResult();

    $this->postJson('/api/sonuclar/'.$result->id.'/ad-goster')->assertStatus(401);

    expect(SystemLog::count())->toBe(0);
});

it('tam ad yalnızca POST ile açılır', function () {
    Sanctum::actingAs(maskUser());
    $result = maskResult();

    $this->getJson('/api/sonuclar/'.$result->id.'/ad-goster')->assertStatus(405);
});

it('olmayan ya da çöp kutusundaki kayıt için tam ad dönmez', function () {
    Sanctum::actingAs(maskUser());
    $result = maskResult();
    $result->delete();

    $this->postJson('/api/sonuclar/'.$result->id.'/ad-goster')->assertStatus(404);
    $this->postJson('/api/sonuclar/999999/ad-goster')->assertStatus(404);

    expect(SystemLog::count())->toBe(0);
});

it('tam ad açma kullanıcı başına dakikada 30 istekle sınırlıdır', function () {
    Sanctum::actingAs(maskUser());
    $result = maskResult();

    foreach (range(1, 30) as $i) {
        $this->postJson('/api/sonuclar/'.$result->id.'/ad-goster')->assertOk();
    }

    $this->postJson('/api/sonuclar/'.$result->id.'/ad-goster')->assertStatus(429);
});

/* ---------------- Düzenleme ve yazdırma ---------------- */

it('düzenleme formu tam adı alır', function () {
    Sanctum::actingAs(maskUser());
    $result = maskResult();

    $this->getJson('/api/sonuclar/'.$result->id)
        ->assertOk()
        ->assertJsonPath('patient.full_name', 'Ayşe Yılmaz');
});

it('yazdırma belgesi tam ad ve kimlik numarasını içerir ve Yazdırma olarak loglanır', function () {
    $user = maskUser();
    Sanctum::actingAs($user);
    $result = maskResult();

    $this->getJson('/api/sonuclar/'.$result->id.'/belge')
        ->assertOk()
        ->assertJsonPath('patient.full_name', 'Ayşe Yılmaz')
        ->assertJsonPath('patient.identity_number', '10000000146');

    $log = SystemLog::sole();

    expect($log->action)->toBe('Yazdırma')
        ->and($log->user_id)->toBe($user->id)
        ->and($log->description)->toBe('LP-TEST0001 barkodlu tahlil sonucu yazdırıldı.');
});

it('personel olmayan kullanıcı kaydı açamaz ve yazdıramaz', function () {
    Sanctum::actingAs(maskUser('misafir'));
    $result = maskResult();

    $this->getJson('/api/sonuclar/'.$result->id)->assertStatus(403);
    $this->getJson('/api/sonuclar/'.$result->id.'/belge')->assertStatus(403);

    expect(SystemLog::count())->toBe(0);
});

it('yazdırma sayfası belge uç noktasını kullanır', function () {
    $this->get('/yazdir/1')->assertOk()->assertSee("'/belge'", false);
});

/* ---------------- Log kayıtları ---------------- */

it('yeni kayıt logunda hasta adı maskeli yazılır', function () {
    Sanctum::actingAs(maskUser());

    $barcode = $this->postJson('/api/sonuclar', [
        'identity_number' => '10000000146',
        'full_name' => 'hüseyin arslan',
        'test_name' => 'Kan Tahlili',
        'result_details' => 'Detaylar',
    ])->assertStatus(201)->json('barcode_number');

    $log = SystemLog::sole();

    expect($log->action)->toBe('Ekleme')
        ->and($log->description)->toBe($barcode.' barkodlu hastanın (H*** A***) tahlil sonucu eklendi.')
        ->and($log->description)->not->toContain('Hüseyin')
        ->and($log->description)->not->toContain('Arslan');
});

it('sistem logları ekranında hasta adı görünmez', function () {
    Sanctum::actingAs(maskUser());
    $this->postJson('/api/sonuclar', [
        'identity_number' => '10000000146',
        'full_name' => 'Fatma Şahin',
        'test_name' => 'Vitamin D',
        'result_details' => 'Normal',
    ])->assertStatus(201);

    Sanctum::actingAs(maskUser('admin'));
    $response = $this->getJson('/api/loglar')->assertOk();

    expect($response->content())->not->toContain('Fatma')
        ->and($response->content())->not->toContain('Şahin');
});

/* ---------------- Çöp kutusu yetkisi ---------------- */

it('laborant çöp kutusunu göremez ve kayıt geri yükleyemez', function () {
    Sanctum::actingAs(maskUser('laborant'));
    $result = maskResult();
    $result->delete();

    $this->getJson('/api/sonuclar/cop-kutusu')->assertStatus(403);
    $this->putJson('/api/sonuclar/'.$result->id.'/geri-yukle')->assertStatus(403);

    expect(TestResult::onlyTrashed()->count())->toBe(1);
});

it('yönetici çöp kutusunu maskeli adlarla görür ve geri yükleyebilir', function () {
    Sanctum::actingAs(maskUser('admin'));
    $result = maskResult();
    $result->delete();

    $response = $this->getJson('/api/sonuclar/cop-kutusu')
        ->assertOk()
        ->assertJsonPath('0.patient.masked_name', 'A*** Y***');

    expect($response->content())->not->toContain('Yılmaz');

    $this->putJson('/api/sonuclar/'.$result->id.'/geri-yukle')->assertOk();

    expect(TestResult::count())->toBe(1);
});

/* ---------------- Eski logları maskeleme komutu ---------------- */

function seedOldLogs(): array
{
    $user = maskUser('admin');

    return [
        'add' => SystemLog::create(['user_id' => $user->id, 'action' => 'Ekleme', 'description' => 'BARKOD-2026-001 barkodlu hastanın (Ayşe Yılmaz) tahlil sonucu eklendi.']),
        'turkish' => SystemLog::create(['user_id' => $user->id, 'action' => 'Ekleme', 'description' => 'BARKOD-2026-006 barkodlu hastanın (Hüseyin Arslan) tahlil sonucu eklendi.']),
        'masked' => SystemLog::create(['user_id' => $user->id, 'action' => 'Ekleme', 'description' => 'LP-ABCD2345 barkodlu hastanın (Z*** K***) tahlil sonucu eklendi.']),
        'delete' => SystemLog::create(['user_id' => $user->id, 'action' => 'Silme', 'description' => 'BARKOD-2026-007 barkodlu tahlil sonucu çöp kutusuna taşındı.']),
    ];
}

it('maskeleme komutu varsayılan olarak yalnızca önizleme yapar, veriyi değiştirmez', function () {
    $logs = seedOldLogs();

    $this->artisan('loglar:ad-maskele')
        ->expectsOutputToContain('yalnızca önizlemedir')
        ->assertSuccessful();

    expect($logs['add']->fresh()->description)->toContain('(Ayşe Yılmaz)')
        ->and($logs['turkish']->fresh()->description)->toContain('(Hüseyin Arslan)');
});

it('maskeleme komutu --uygula ile yalnızca açık adları maskeler', function () {
    $logs = seedOldLogs();
    $createdAt = $logs['add']->created_at->toDateTimeString();

    $this->artisan('loglar:ad-maskele', ['--uygula' => true])->assertSuccessful();

    expect($logs['add']->fresh()->description)->toBe('BARKOD-2026-001 barkodlu hastanın (A*** Y***) tahlil sonucu eklendi.')
        ->and($logs['turkish']->fresh()->description)->toBe('BARKOD-2026-006 barkodlu hastanın (H*** A***) tahlil sonucu eklendi.')
        ->and($logs['masked']->fresh()->description)->toBe('LP-ABCD2345 barkodlu hastanın (Z*** K***) tahlil sonucu eklendi.')
        ->and($logs['delete']->fresh()->description)->toBe('BARKOD-2026-007 barkodlu tahlil sonucu çöp kutusuna taşındı.')
        ->and($logs['add']->fresh()->created_at->toDateTimeString())->toBe($createdAt);

    // İkinci çalıştırmada değişecek bir şey kalmaz
    $this->artisan('loglar:ad-maskele')
        ->expectsOutputToContain('Maskelenecek log kaydı yok.')
        ->assertSuccessful();
});
