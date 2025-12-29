<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOwnerReadOnly
{
    /**
     * Handle an incoming request.
     * 
     * Middleware untuk memastikan Owner hanya bisa akses GET request
     * Owner tidak boleh CREATE, UPDATE, DELETE
     * 
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Kalau user adalah owner
        if ($user && $user->role === 'owner_inventory') {
            
            // Cek apakah method nya write operation (POST, PUT, PATCH, DELETE)
            if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden. Owner hanya memiliki akses read-only.',
                    'allowed_methods' => ['GET'],
                    'your_method' => $request->method(),
                ], 403);
            }
        }

        return $next($request);
    }
}