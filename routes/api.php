<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TestResultController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Herkese açık rotalar. Her ikisi de deneme yanılma saldırılarına açık olduğu
// için IP başına dakikalık istek sınırı uygulanıyor.
Route::post('/sonuc/{barcode}', [TestResultController::class, 'getResultByBarcode'])
    ->middleware('throttle:10,1');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

// Sadece giriş yapmış (token'ı olan) kullanıcıların erişebileceği rotalar
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]);
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    // Tüm sonuçları listeleme
    Route::get('/sonuclar', [TestResultController::class, 'getAllResults']);

    // Dashboard istatistikleri
    Route::get('/istatistikler', [TestResultController::class, 'getStatistics']);

    // Yeni kayıt ekleme
    Route::post('/sonuclar', [TestResultController::class, 'store']);

    // Çöp kutusu rotaları parametreli rotalardan önce tanımlanmalı
    Route::get('/sonuclar/cop-kutusu', [TestResultController::class, 'getTrashedResults']);
    Route::put('/sonuclar/{id}/geri-yukle', [TestResultController::class, 'restore'])
        ->whereNumber('id');

    Route::get('/sonuclar/{id}', [TestResultController::class, 'show'])->whereNumber('id');
    Route::put('/sonuclar/{id}', [TestResultController::class, 'update'])->whereNumber('id');

    // Silme ve sistem logları yalnızca yönetici rolündeki kullanıcılara açık
    Route::middleware('admin')->group(function () {
        Route::delete('/sonuclar/{id}', [TestResultController::class, 'destroy'])->whereNumber('id');
        Route::get('/loglar', [TestResultController::class, 'getLogs']);
    });
});
