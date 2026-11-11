<?php

use App\Models\Patient;

it('her kelimenin ilk harfini bırakır, gerisini sabit 3 yıldızla gizler', function (string $name, string $expected) {
    expect(Patient::maskName($name))->toBe($expected);
})->with([
    'iki kelime' => ['Ayşe Yılmaz', 'A*** Y***'],
    'uzun ad' => ['Hüseyin Arslan', 'H*** A***'],
    'üç kelime' => ['Mehmet Ali Demir', 'M*** A*** D***'],
    'tek kelime' => ['Zeynep', 'Z***'],
    'tek harfli kelime' => ['Ali K', 'A*** K***'],
]);

it('Türkçe ilk harfleri bozmaz', function (string $name, string $expected) {
    expect(Patient::maskName($name))->toBe($expected);
})->with([
    ['Şule Çelik', 'Ş*** Ç***'],
    ['İsmail Ünal', 'İ*** Ü***'],
    ['Ömer Öztürk', 'Ö*** Ö***'],
    ['Ğ Ğ', 'Ğ*** Ğ***'],
]);

it('yıldız sayısı adın uzunluğunu ele vermez', function () {
    expect(Patient::maskName('Al Bo'))->toBe(Patient::maskName('Alexandrina Bonaventura'));
});

it('fazla boşlukları yok sayar', function () {
    expect(Patient::maskName("  Ayşe \t  Yılmaz  "))->toBe('A*** Y***');
});

it('boş ad için tire döner', function (?string $name) {
    expect(Patient::maskName($name))->toBe('-');
})->with([null, '', '   ']);

it('masked_name alanı modelde hazır gelir', function () {
    $patient = new Patient(['full_name' => 'Fatma Şahin']);

    expect($patient->masked_name)->toBe('F*** Ş***');
});
