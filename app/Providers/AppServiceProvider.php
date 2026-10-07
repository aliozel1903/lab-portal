<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Laravel'in isimsiz "throttle:x,y" sınırı sayacı yalnızca IP'ye göre
        // tutar; hasta sorgusu ile personel girişi aynı sayacı paylaşır ve
        // birkaç hatalı sorgu, aynı ağdaki personelin girişini kilitler.
        // Her uç noktaya kendi sayacını veriyoruz.
        RateLimiter::for('giris', fn (Request $request) => Limit::perMinute(5)->by('giris|'.$request->ip()));

        RateLimiter::for('hasta-sorgu', fn (Request $request) => Limit::perMinute(10)->by('hasta-sorgu|'.$request->ip()));
    }
}
