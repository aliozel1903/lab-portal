<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
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
