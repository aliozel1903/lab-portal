<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Yalnızca 'admin' rolündeki kullanıcıların geçebildiği rotalar için.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json([
                'message' => 'Bu işlem için yönetici yetkisi gerekiyor.',
            ], 403);
        }

        return $next($request);
    }
}
