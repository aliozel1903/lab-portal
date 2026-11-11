<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Yalnızca laboratuvar personelinin (yönetici veya laborant) geçebildiği
 * rotalar için. Hastanın tam adı gibi kişisel verileri açan uç noktalarda
 * kullanılır; başka bir role sahip ya da rolü olmayan hesap 403 alır.
 */
class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isStaff()) {
            return response()->json([
                'message' => 'Bu işlem için laboratuvar personeli yetkisi gerekiyor.',
            ], 403);
        }

        return $next($request);
    }
}
