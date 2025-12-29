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
     * Hitung total stok produk (akumulasi batch)
     */
    public function getTotalStock(int $productId): float
    {
        return ProductStock::where('product_id', $productId)
            ->sum('qty');
    }

    /**
     * Hitung rata-rata penjualan harian
     */
    public function getAverageDailySales(int $productId, int $days = 30): float
    {
        $startDate = Carbon::now()->subDays($days);

        $totalSold = StockMovement::where('tipe', 'keluar')
            ->where('sumber', 'penjualan')
            ->whereHas('productStock', function ($q) use ($productId) {
                $q->where('product_id', $productId);
            })
            ->where('created_at', '>=', $startDate)
            ->sum('qty');

        return $days > 0 ? $totalSold / $days : 0;
    }

    /**
     * Hitung ROP produk
     */
    public function calculateROP(int $productId): float
    {
        $planning = ProductPlanning::where('product_id', $productId)->firstOrFail();

        $avgDailySales = $this->getAverageDailySales($productId);

        return ($avgDailySales * $planning->lead_time_days)
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
