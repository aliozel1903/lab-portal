<?php

use Illuminate\Support\Facades\Route;

// Tek sayfalık portal: hasta sorgulama, laborant girişi ve yönetim paneli
// aynı ekranda, sayfa yenilenmeden bölümler arası geçişle çalışır.
Route::get('/', function () {
    return view('portal');
})->name('portal');

// Eski adresler kayıtlı bağlantıların kırılmaması için portala yönlendirilir
Route::redirect('/login', '/');
Route::redirect('/admin', '/');

// Tahlil sonucu yazdırma ekranı (veriler token ile API'den çekilir)
Route::get('/yazdir/{id}', function ($id) {
    return view('print', ['id' => $id]);
})->whereNumber('id');
