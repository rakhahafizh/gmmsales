<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * Memastikan hanya user dengan role 'admin' yang dapat mengakses
     * endpoint yang diproteksi middleware ini. Jika bukan admin,
     * request akan ditolak dengan response 403 Forbidden.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Jika tidak ada user (token invalid) tangani di sini juga
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Cek role admin pakai helper yang sudah ada di User model
        if (!$user->isAdmin()) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke halaman ini',
            ], 403);
        }

        return $next($request);
    }
}