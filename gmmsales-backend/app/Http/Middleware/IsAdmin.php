<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        if (!$user->isAdmin()) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke halaman ini',
            ], 403);
        }

        return $next($request);
    }
}