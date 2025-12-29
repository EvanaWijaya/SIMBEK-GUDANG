<?php

namespace App\Services;

use App\Models\Production;
use App\Models\Product;
use App\Models\Formula;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * ========================================
 * PRODUCTION PROCESS SERVICE
 * ========================================
 * 
 * Handle core production execution & processing
 * Responsibilities: Create production, Execute workflow, Status management
 * 
 * Author: SIMBEK Team
 * Version: 2.0
 */
class ProductionProcessService
{
    protected StockService $stockService;
    protected FormulaCostService $costService;

    public function __construct(
        StockService $stockService,
        FormulaCostService $costService
    ) {
        $this->stockService = $stockService;
        $this->costService = $costService;
    }

    /**
     * Process produksi lengkap
     */
    public function processProduction(array $data): Production
    {
        // Validate input
        $this->validateProductionData($data);

        // Get formula & validate
        $formula = Formula::with('details.material')->findOrFail($data['formula_id']);
        
        if (!$formula->is_active) {
            throw new Exception("Formula '{$formula->nama_formula}' tidak aktif");
        }

        // Validate product matches formula
        if ($formula->product_id != $data['product_id']) {
            throw new Exception("Formula tidak sesuai dengan produk yang dipilih");
        }

        // Check material availability
        $validation = $this->costService->validateStockForProduction(
            $data['formula_id'], 
            $data['jumlah']
        );

        if (!$validation['is_sufficient']) {
            $insufficientMaterials = array_column($validation['insufficient_materials'], 'nama_material');
            throw new Exception(
                "Stok material tidak mencukupi untuk produksi. " .
                "Material kurang: " . implode(', ', $insufficientMaterials)
            );
        }

        return DB::transaction(function () use ($data, $formula) {
            // 1. Create production record (status: pending)
            $production = Production::create([
                'product_id' => $data['product_id'],
                'formula_id' => $data['formula_id'],
                'tgl_produksi' => $data['tgl_produksi'],
                'jumlah' => $data['jumlah'],
                'satuan' => 'kg',
                'expired_date' => $data['expired_date'] ?? $this->calculateExpiredDate($data['product_id']),
                'status' => 'pending',
                'user_id' => $data['user_id'],
            ]);

            // 2. Deduct materials from stock
            foreach ($formula->details as $detail) {
                $neededQty = $detail->qty * $data['jumlah'];
                
                $this->stockService->deductMaterialStock(
                    $detail->material_id,
                    $neededQty,
                    'production',
                    $production->id
                );
            }

            // 3. Update status to selesai
            $production->update(['status' => 'selesai']);

            // 4. Create product stock
            $productStock = $this->stockService->addProductStock(
                $data['product_id'],
                $production->id,
                $data['jumlah']
            );

            // 5. Record product stock movement
            $this->stockService->recordMovement([
                'tipe' => 'masuk',
                'sumber' => 'production',
                'qty' => $data['jumlah'],
                'product_stock_id' => $productStock->id,
                'ref_id' => $production->id,
                'notes' => "Produksi {$formula->product->nama_produk}",
            ]);

            return $production->fresh(['product', 'formula', 'user', 'productStocks']);
        });
    }

    /**
     * Update production status
     */
    public function updateStatus(int $productionId, string $status): Production
    {
        $production = Production::findOrFail($productionId);

        // Validate status transition
        $allowedTransitions = [
            'pending' => ['selesai', 'batal'],
            'selesai' => [],
            'batal' => [],
        ];

        if (!in_array($status, $allowedTransitions[$production->status] ?? [])) {
            throw new Exception(
                "Status tidak bisa diubah dari '{$production->status}' ke '{$status}'"
            );
        }

        $production->update(['status' => $status]);

        return $production;
    }

    /**
     * Cancel production (hanya untuk status pending)
     */
    public function cancelProduction(int $productionId, int $userId): Production
    {
        $production = Production::with('formula.details')->findOrFail($productionId);

        if ($production->status !== 'pending') {
            throw new Exception("Hanya produksi dengan status 'pending' yang bisa dibatalkan");
        }

        return DB::transaction(function () use ($production) {
            // Return materials to stock
            foreach ($production->formula->details as $detail) {
                $returnedQty = $detail->qty * $production->jumlah;
                
                $this->stockService->addMaterialStock(
                    $detail->material_id,
                    $returnedQty,
                    'production_cancelled',
                    $production->id
                );
            }

            // Update status
            $production->update(['status' => 'batal']);

            return $production;
        });
    }

    /**
     * Validate production data
     */
    private function validateProductionData(array $data): void
    {
        if (!isset($data['product_id']) || !isset($data['formula_id']) || !isset($data['jumlah'])) {
            throw new Exception("Data produksi tidak lengkap");
        }

        if ($data['jumlah'] <= 0) {
            throw new Exception("Jumlah produksi harus lebih dari 0");
        }

        Product::findOrFail($data['product_id']);
    }

    /**
     * Calculate expired date based on product type
     */
    private function calculateExpiredDate(int $productId): string
    {
        $product = Product::findOrFail($productId);
        
        $months = match(strtolower($product->kategori)) {
            'pakan' => 6,
            'obat' => 24,
            'suplemen' => 18,
            default => 12,
        };

        return now()->addMonths($months)->format('Y-m-d');
    }
}