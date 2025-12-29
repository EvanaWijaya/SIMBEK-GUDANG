<?php

namespace App\Services\Stock;

use App\Events\StockBelowROP;
use App\Models\ProductPlanning;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Carbon\Carbon;

class ProductPlanningService
{
    /**
     * Total stok produk (akumulasi batch)
     */
    public function getTotalStock(int $productId): float
    {
        return ProductStock::where('product_id', $productId)
            ->sum('qty');
    }

    /**
     * Hitung rata-rata demand harian
     * (penjualan + pemakaian internal)
     */
    public function getAverageDailyDemand(
        int $productId,
        int $days = 30
    ): float {
        $startDate = Carbon::now()->subDays($days);

        $totalDemand = StockMovement::where('tipe', 'keluar')
            ->whereIn('sumber', ['penjualan', 'pemakaian_internal'])
            ->whereHas('productStock', function ($q) use ($productId) {
                $q->where('product_id', $productId);
            })
            ->where('created_at', '>=', $startDate)
            ->sum('qty');

        return $days > 0 ? $totalDemand / $days : 0;
    }

    /**
     * Hitung ROP produk
     */
    public function calculateROP(int $productId): float
    {
        $planning = ProductPlanning::where('product_id', $productId)
            ->firstOrFail();

        $avgDailyDemand = $this->getAverageDailyDemand($productId);

        return ($avgDailyDemand * $planning->lead_time_days)
            + $planning->safety_stock;
    }

    /**
     * Cek apakah produk perlu diproduksi
     */
    public function needsProduction(int $productId): bool
    {
        $totalStock = $this->getTotalStock($productId);
        $rop        = $this->calculateROP($productId);

        if ($totalStock <= $rop) {
            event(new StockBelowROP(
                'product',
                $productId,
                $totalStock,
                $rop
            ));
            return true;
        }

        return false;
    }
}
