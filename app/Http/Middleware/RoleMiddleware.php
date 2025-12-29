<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     * 
     * Middleware untuk cek role user
     * Usage: Route::middleware('role:admin_inventory')
     * 
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Cek apakah user authenticated
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Cek apakah user punya salah satu role yang diizinkan
        if (!in_array($request->user()->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Anda tidak memiliki akses ke resource ini.',
                'required_role' => $roles,
                'your_role' => $request->user()->role,
            ], 403);
        }

        return $next($request);
    }
}