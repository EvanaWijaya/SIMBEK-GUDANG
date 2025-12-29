<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductionPlanningService;
use App\Services\Production\ProductionService;
use App\Services\Stock\ProductStockService;
use App\Services\UsageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductionController extends Controller
{
    protected ProductionService $productionService;
    protected ProductionPlanningService $planningService;
    protected ProductStockService $productStockService;
    protected UsageService $usageService;

    public function __construct(
        ProductionService $productionService,
        ProductionPlanningService $planningService,
        ProductStockService $productStockService,
        UsageService $usageService
    ) {
        $this->productionService   = $productionService;
        $this->planningService     = $planningService;
        $this->productStockService = $productStockService;
        $this->usageService        = $usageService;
    }

    /**
     * ========================================
     * PRODUCTION PLANNING (ROP CHECK)
     * ========================================
     */
    public function plan(Request $request): JsonResponse
    {
        $request->validate([
            'formula_id' => 'required|integer|exists:formulas,id',
        ]);

        $result = $this->planningService
            ->analyzeProductionWithROP($request->formula_id);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * ========================================
     * EXECUTE PRODUCTION
     * ========================================
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'formula_id' => 'required|integer|exists:formulas,id',
            'qty' => 'required|numeric|min:0.01',
            'purpose' => 'required|in:penjualan,pemakaian_internal',
        ]);

        DB::beginTransaction();

        try {
            // 1. Simulasi & validasi stok + ROP
            $simulation = $this->planningService->simulateProduction(
                $validated['formula_id'],
                (float) $validated['qty']
            );

            if (!$simulation['can_produce']) {
                throw new \Exception(
                    'Produksi ditolak: stok material tidak mencukupi'
                );
            }

            // 2. Eksekusi produksi (potong material, tambah produk)
            $production = $this->productionService->produceByFormula(
                $validated['formula_id'],
                (float) $validated['qty']
            );

            // 3. Jika langsung dipakai internal
            if ($validated['purpose'] === 'pemakaian_internal') {
                $this->usageService->recordUsage([
                    'product_id' => $production->product_id,
                    'qty' => $validated['qty'],
                    'usage_type' => 'feed',
                    'animal_type' => 'kambing',
                    'user_id' => auth()->id() ?? 1,
                ]);
            }

            DB::commit();

            Log::info('Production success', [
                'production_id' => $production->id,
                'purpose' => $validated['purpose'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $production,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Production failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
