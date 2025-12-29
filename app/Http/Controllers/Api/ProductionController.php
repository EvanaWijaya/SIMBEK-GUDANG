<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Production\ProductionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ProductionController extends Controller
{
    protected ProductionService $productionService;

    public function __construct(ProductionService $productionService)
    {
        $this->productionService = $productionService;
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'qty' => 'required|numeric|min:0.01',
        ]);

        try {
            $production = $this->productionService->produce(
                $validated['product_id'],
                (float) $validated['qty']
            );

            Log::info('Production created', [
                'production_id' => $production->id,
            ]);

            return response()->json([
                'success' => true,
                'data' => $production,
            ], 201);

        } catch (\Throwable $e) {
            Log::error('Production error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}