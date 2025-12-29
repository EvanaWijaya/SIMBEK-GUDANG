<?php

namespace App\Services\Production;

use App\Models\Production;
use App\Services\Stock\ProductPlanningService;
use App\Services\Stock\ProductStockService;
use App\Services\Stock\MaterialConsumptionService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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

    public function produce(int $productId, float $qty)
    {
        return DB::transaction(function () use ($productId, $qty) {

            // ✅ SIMPAN PRODUKSI
            $production = Production::create([
                'product_id' => $productId,
                'qty' => $qty,
            ]);

            $productionId = $production->id;

            // ✅ TAMBAH STOK PRODUK
            $this->productStockService->addStockFromProduction(
                $productId,
                $productionId,
                $qty
            );

            // ✅ KONSUMSI MATERIAL
            $this->materialConsumptionService->consumeForProduction(
                $productId,
                $qty,
                $productionId
            );

            return $production;
        });
    }
}
