<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsStaff;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Uygulama PHP oturumu kullanmaz: giriş, API'de token ile yapılır ve
        // web rotaları yalnızca sayfa döndürür (form gönderen web rotası
        // yoktur). Oturum başlatılırsa Laravel her ziyaretçi için veritabanına
        // IP ve tarayıcı bilgisiyle bir satır yazar; bunu önlemek için oturum,
        // oturuma bağlı hata paylaşımı ve CSRF ara katmanlarını kaldırıyoruz.
        $middleware->web(remove: [
            StartSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'staff' => EnsureUserIsStaff::class,
        ]);

        // Vercel'de istekler fonksiyona Vercel'in kendi aracısı üzerinden
        // gelir; aracıya güvenilmezse her ziyaretçinin IP'si 127.0.0.1 görünür
        // ve IP başına istek sınırı herkes için ortak tek bir sayaca dönüşür.
        // Vercel X-Forwarded-For başlığını kendisi yazdığı için ziyaretçi bu
        // başlığı taklit edemez. Bayrak yalnızca api/index.php'de konur.
        if (($_SERVER['LAB_PORTAL_TRUST_PROXY'] ?? null) === '1') {
            $middleware->trustProxies(at: '*');
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
