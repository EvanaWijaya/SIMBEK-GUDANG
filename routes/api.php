<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\FormulaController;
use App\Http\Controllers\Api\ProductionController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StockDisposalController;

/*
|--------------------------------------------------------------------------
| API Routes - SIMBEK Inventory System
|--------------------------------------------------------------------------
*/

// ==============================
// PUBLIC ROUTES
// ==============================
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// ==============================
// PROTECTED ROUTES
// ==============================
Route::middleware('auth:sanctum')->group(function () {

    // ==========================
    // AUTH
    // ==========================
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // =====================================================
    // ADMIN INVENTORY (FULL ACCESS - CUD)
    // =====================================================
    Route::middleware(['role:admin_inventory'])->group(function () {

        // ==========================
        // MATERIALS
        // ==========================
        Route::apiResource('materials', MaterialController::class)
            ->except(['index', 'show']);

        // ==========================
        // PRODUCTS
        // ==========================
        Route::apiResource('products', ProductController::class)
            ->except(['index', 'show']);

        // ==========================
        // FORMULAS
        // ==========================
        Route::apiResource('formulas', FormulaController::class)
            ->except(['index', 'show']);

        // ==========================
        // PRODUCTION
        // ==========================
        /**
         * Produksi produk jadi:
         * - sistem otomatis pakai formula aktif
         * - konsumsi material
         * - update stok produk
         * - cek ROP produk & material
         */
        Route::post('productions', [ProductionController::class, 'store']);

        // ==========================
        // SALES
        // ==========================
        Route::post('sales', [SaleController::class, 'store']);

        // ==========================
        // DISPOSAL (Rusak / Expired)
        // ==========================
        Route::post('disposals', [StockDisposalController::class, 'store']);
    });

    // =====================================================
    // ADMIN + OWNER (READ ONLY)
    // =====================================================
    Route::middleware(['role:admin_inventory,owner_inventory', 'owner.readonly'])
        ->group(function () {

        // ==========================
        // MATERIALS
        // ==========================
        Route::get('materials', [MaterialController::class, 'index']);
        Route::get('materials/{material}', [MaterialController::class, 'show']);

        // ==========================
        // PRODUCTS
        // ==========================
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{product}', [ProductController::class, 'show']);

        // ==========================
        // FORMULAS
        // ==========================
        Route::get('formulas', [FormulaController::class, 'index']);
        Route::get('formulas/{formula}', [FormulaController::class, 'show']);

        // ==========================
        // PRODUCTION
        // ==========================
        Route::get('productions', [ProductionController::class, 'index']);
        Route::get('productions/{production}', [ProductionController::class, 'show']);

        // ==========================
        // SALES
        // ==========================
        Route::get('sales', [SaleController::class, 'index']);
        Route::get('sales/{sale}', [SaleController::class, 'show']);

        // ==========================
        // DISPOSAL
        // ==========================
        Route::get('disposals', [StockDisposalController::class, 'index']);

        // ==========================
        // ANALYTICS & MONITORING
        // ==========================
        Route::prefix('analytics')->group(function () {
            Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
        });

        // ==========================
        // ACTIVITY LOGS
        // ==========================
        Route::get('activity-logs', [ActivityLogController::class, 'index']);
        Route::get('activity-logs/{id}', [ActivityLogController::class, 'show']);
    });
});

// ==============================
// HEALTH CHECK
// ==============================
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is running',
        'timestamp' => now()->format('Y-m-d H:i:s'),
    ]);
});
