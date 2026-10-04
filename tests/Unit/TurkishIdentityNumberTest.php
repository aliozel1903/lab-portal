<?php

use App\Rules\TurkishIdentityNumber;
use Illuminate\Support\Facades\Validator;

/**
 * Geçerli bir T.C. Kimlik Numarası üretir (test verisi için).
 */
function makeIdentityNumber(array $firstNine): string
{
    $d = $firstNine;

    $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
    $even = $d[1] + $d[3] + $d[5] + $d[7];

    $d[9] = (((($odd * 7) - $even) % 10) + 10) % 10;
    $d[10] = array_sum(array_slice($d, 0, 10)) % 10;

    return implode('', $d);
}

/**
 * Geçerli bir numaranın son hanesini bir artırarak demo modu için test
 * numarası üretir. Sonuç her zaman tek rakamla biter, gerçek olamaz.
 */
function makeTestIdentityNumber(array $firstNine): string
{
    $real = makeIdentityNumber($firstNine);

    return substr($real, 0, 10).((int) $real[10] + 1);
}

function passesIdentityRule(mixed $value, bool $demoMode = false): bool
{
    return Validator::make(
        ['identity_number' => $value],
        ['identity_number' => [new TurkishIdentityNumber($demoMode)]]
    )->passes();
}

it('geçerli bir kimlik numarasını kabul eder', function () {
    expect(passesIdentityRule(makeIdentityNumber([1, 0, 0, 0, 0, 0, 0, 0, 0])))->toBeTrue();
});

it('harf veya kelime içeren girdileri reddeder', function (string $value) {
    expect(passesIdentityRule($value))->toBeFalse();
})->with(['ahmet', 'abc12345678', '1234567890a', 'onbir hane!', '', '   ']);

it('11 haneli olmayan numaraları reddeder', function (string $value) {
    expect(passesIdentityRule($value))->toBeFalse();
})->with(['1', '1234567890', '123456789012']);

it('sıfır ile başlayan numaraları reddeder', function () {
    $valid = makeIdentityNumber([1, 2, 3, 4, 5, 6, 7, 8, 9]);

    expect(passesIdentityRule('0'.substr($valid, 1)))->toBeFalse();
});

it('doğrulama algoritmasından geçmeyen numaraları reddeder', function () {
    // Yaygın olarak denenen ama algoritmaya uymayan numaralar
    expect(passesIdentityRule('12345678901'))->toBeFalse()
        ->and(passesIdentityRule('11111111111'))->toBeFalse();
});

it('son hanesi bozulmuş numaraları reddeder', function () {
    $valid = makeIdentityNumber([2, 4, 6, 8, 1, 3, 5, 7, 9]);
    $lastDigit = (int) $valid[10];
    $broken = substr($valid, 0, 10).(($lastDigit + 1) % 10);

    expect(passesIdentityRule($valid))->toBeTrue()
        ->and(passesIdentityRule($broken))->toBeFalse();
});

it('geçerli numaralar daima çift rakamla biter', function () {
    // 11. hane kuralının doğal sonucu: tek rakamla biten geçerli TCKN yoktur
    for ($i = 0; $i < 500; $i++) {
        $digits = [random_int(1, 9)];

        for ($j = 0; $j < 8; $j++) {
            $digits[] = random_int(0, 9);
        }

        $number = makeIdentityNumber($digits);

        expect(passesIdentityRule($number))->toBeTrue()
            ->and((int) $number[10] % 2)->toBe(0);
    }
});

/* ---------------- Demo modu ---------------- */

it('demo modunda gerçek olabilecek numarayı reddeder', function () {
    expect(passesIdentityRule(makeIdentityNumber([1, 2, 3, 4, 5, 6, 7, 8, 9]), demoMode: true))->toBeFalse();
});

it('demo modunda son hanesi bir fazla olan test numarasını kabul eder', function () {
    expect(passesIdentityRule(makeTestIdentityNumber([1, 2, 3, 4, 5, 6, 7, 8, 9]), demoMode: true))->toBeTrue();
});

it('normal modda test numarasını reddeder', function () {
    expect(passesIdentityRule(makeTestIdentityNumber([1, 2, 3, 4, 5, 6, 7, 8, 9])))->toBeFalse();
});

it('demo modunda da temel kuralları uygular', function (string $value) {
    expect(passesIdentityRule($value, demoMode: true))->toBeFalse();
})->with(['ahmet', '123', '01234567891', '12345678901']);

it('demo modunda son hanesi rastgele tek olan numarayı reddeder', function () {
    $real = makeIdentityNumber([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    $wrongOdd = substr($real, 0, 10).(((int) $real[10] + 3) % 10);

    expect(passesIdentityRule($wrongOdd, demoMode: true))->toBeFalse();
});

it('demo modunda kabul edilen her numara tek rakamla biter', function () {
    // Gerçek numaralar daima çift bittiği için hiçbiri gerçek bir kişiye ait olamaz
    for ($i = 0; $i < 500; $i++) {
        $digits = [random_int(1, 9)];

        for ($j = 0; $j < 8; $j++) {
            $digits[] = random_int(0, 9);
        }

        $number = makeTestIdentityNumber($digits);

        expect(passesIdentityRule($number, demoMode: true))->toBeTrue()
            ->and((int) $number[10] % 2)->toBe(1);
    }
});
