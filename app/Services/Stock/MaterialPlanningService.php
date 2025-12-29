<?php

namespace App\Services\Stock;

use App\Events\StockBelowROP;
use App\Models\Material;
use App\Models\StockMovement;
use Carbon\Carbon;

class MaterialPlanningService
{
    /**
     * Hitung rata-rata pemakaian harian material
     */
    public function getAverageDailyUsage(int $materialId, int $days = 30): float
    {
        $startDate = Carbon::now()->subDays($days);

        $totalUsed = StockMovement::where('material_id', $materialId)
            ->where('tipe', 'keluar')
            ->where('created_at', '>=', $startDate)
            ->sum('qty');

        return $days > 0 ? $totalUsed / $days : 0;
    }

    /**
     * Hitung ROP material
     */
    public function calculateROP(Material $material): float
    {
        $avgDailyUsage = $this->getAverageDailyUsage($material->id);

        return ($avgDailyUsage * $material->lead_time_days)
            + $material->safety_stock;
    }

    /**
     * Cek apakah material perlu direstock
     */
    public function needsRestock(Material $material): bool
    {
        $rop = $this->calculateROP($material);

        if ($material->stok <= $rop) {
            event(new StockBelowROP(
                'material',
                $material->id,
                (float) $material->stok,
                $rop
            ));
            return true;
        }

        return false;
    }
}
