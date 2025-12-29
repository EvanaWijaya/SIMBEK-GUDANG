<?php

namespace App\Services\Production;

use App\Models\Production;
use App\Models\Formula;
use App\Services\Stock\ProductPlanningService;
use App\Services\Stock\ProductStockService;
use App\Services\Stock\MaterialConsumptionService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Exception;

class ProductionService
{
    protected ProductPlanningService $planningService;
    protected ProductStockService $productStockService;
    protected MaterialConsumptionService $materialConsumptionService;

    public function __construct(
        ProductPlanningService $planningService,
        ProductStockService $productStockService,
        MaterialConsumptionService $materialConsumptionService
    ) {
        $this->planningService = $planningService;
        $this->productStockService = $productStockService;
        $this->materialConsumptionService = $materialConsumptionService;
    }

    /**
     * ========================================
     * PRODUKSI BERBASIS FORMULA (RECOMMENDED)
     * ========================================
     */
    public function produceByFormula(int $formulaId, float $qty): Production
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty produksi harus lebih dari 0');
        }

        return DB::transaction(function () use ($formulaId, $qty) {

            $formula = Formula::with('product')
                ->findOrFail($formulaId);

            $productId = $formula->product_id;

            /**
             * 1️⃣ SIMPAN DATA PRODUKSI
             */
            $production = Production::create([
                'product_id' => $productId,
                'formula_id' => $formulaId,
                'qty'        => $qty,
                'status'     => 'completed',
            ]);

            /**
             * 2️⃣ KONSUMSI MATERIAL (BERDASARKAN FORMULA)
             */
            $this->materialConsumptionService->consumeForProduction(
                $formulaId,
                $qty,
                $production->id
            );

            /**
             * 3️⃣ TAMBAH STOK PRODUK (PER BATCH)
             */
            $this->productStockService->addStockFromProduction(
                $productId,
                $production->id,
                $qty
            );

            return $production;
        });
    }

    /**
     * ========================================
     * PRODUKSI LEGACY (TETAP DISUPPORT)
     * ========================================
     * Dipertahankan agar tidak merusak fitur lama
     */
    public function produce(int $productId, float $qty): Production
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty produksi harus lebih dari 0');
        }

        return DB::transaction(function () use ($productId, $qty) {

            /**
             * 1️⃣ SIMPAN PRODUKSI
             */
            $production = Production::create([
                'product_id' => $productId,
                'qty'        => $qty,
                'status'     => 'completed',
            ]);

            /**
             * 2️⃣ TAMBAH STOK PRODUK
             */
            $this->productStockService->addStockFromProduction(
                $productId,
                $production->id,
                $qty
            );

            /**
             * 3️⃣ KONSUMSI MATERIAL (MODE LAMA)
             */
            $this->materialConsumptionService->consumeForProduction(
                $productId,
                $qty,
                $production->id
            );

            return $production;
        });
    }
}
