<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    /**
     * Login
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Login gagal'
            ], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;

        // ✅ ACTIVITY LOG
        $this->activityLogService->log(
            $user->id,
            'login',
            'User login ke sistem',
        );

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'token' => $token,
            'user' => $user
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->activityLogService->log(
            $user->id,
            'logout',
            'User logout dari sistem',
        );

        $user->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }

    /**
     * Me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    }
}
