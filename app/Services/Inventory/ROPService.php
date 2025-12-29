<?php

namespace App\Services\Inventory;

use App\Models\Material;
use App\Models\StockMovement;

/**
 * ========================================
 * ROP SERVICE (Reorder Point)
 * ========================================
 * 
 * Responsibility: ROP calculation ONLY
 * - Calculate daily usage
 * - Calculate ROP
 * - Check if restock needed
 * 
 * Formula: ROP = (Lead Time × Daily Usage) + Safety Stock
 * 
 * Daily Usage: Based on last 30 days stock movements
 * 
 * @package App\Services\Inventory
 * @version 2.0 (Refactored)
 */
class ROPService
{
    /**
     * Calculate daily usage for material
     * Based on last 30 days
     * 
     * Formula: Daily Usage = Total Qty Out (30 days) / 30
     * 
     * @param int $materialId
     * @return float kg/day
     */
    public function calculateDailyUsage(int $materialId): float
    {
        $thirtyDaysAgo = now()->subDays(30);

        $totalUsage = StockMovement::where('material_id', $materialId)
            ->where('tipe', 'keluar')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->sum('qty');

        return $totalUsage / 30;
    }

    /**
     * Calculate ROP (Reorder Point)
     * 
     * Formula: ROP = (Lead Time × Daily Usage) + Safety Stock
     * 
     * @param int $materialId
     * @return float kg
     */
    public function calculateROP(int $materialId): float
    {
        $material = Material::findOrFail($materialId);
        $dailyUsage = $this->calculateDailyUsage($materialId);

        $rop = ($material->lead_time_days * $dailyUsage) + $material->safety_stock;

        return round($rop, 2);
    }

    /**
     * Check if material needs restock
     * 
     * @param int $materialId
     * @return bool
     */
    public function needsRestock(int $materialId): bool
    {
        $material = Material::findOrFail($materialId);
        $rop = $this->calculateROP($materialId);

        return $material->stok <= $rop;
    }

    /**
     * Calculate days until stockout
     * 
     * Formula: Days Until Stockout = Current Stock / Daily Usage
     * 
     * @param int $materialId
     * @return float|null (null if no usage)
     */
    public function calculateDaysUntilStockout(int $materialId): ?float
    {
        $material = Material::findOrFail($materialId);
        $dailyUsage = $this->calculateDailyUsage($materialId);

        if ($dailyUsage <= 0) {
            return null; // No usage pattern
        }

        return round($material->stok / $dailyUsage, 0);
    }

    /**
     * Get usage history for material
     * 
     * @param int $materialId
     * @param int $days
     * @return array
     */
    public function getUsageHistory(int $materialId, int $days = 30): array
    {
        $movements = StockMovement::where('material_id', $materialId)
            ->where('tipe', 'keluar')
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get();

        return $movements->map(function($movement) {
            return [
                'date' => $movement->created_at->format('Y-m-d'),
                'qty' => $movement->qty,
                'source' => $movement->sumber,
            ];
        })->toArray();
    }

    /**
     * Get ROP details for material
     * 
     * @param int $materialId
     * @return array
     */
    public function getROPDetails(int $materialId): array
    {
        $material = Material::findOrFail($materialId);
        $dailyUsage = $this->calculateDailyUsage($materialId);
        $rop = $this->calculateROP($materialId);
        $needsRestock = $this->needsRestock($materialId);
        $daysUntilStockout = $this->calculateDaysUntilStockout($materialId);

        return [
            'material_id' => $materialId,
            'material_name' => $material->nama_material,
            'current_stock' => $material->stok,
            'safety_stock' => $material->safety_stock,
            'lead_time_days' => $material->lead_time_days,
            'daily_usage' => round($dailyUsage, 2),
            'rop' => $rop,
            'needs_restock' => $needsRestock,
            'days_until_stockout' => $daysUntilStockout,
            'status' => $this->getStockStatus($material, $rop, $daysUntilStockout),
        ];
    }

    /**
     * Get stock status based on ROP and days until stockout
     */
    private function getStockStatus(Material $material, float $rop, ?float $daysUntilStockout): string
    {
        if ($material->stok <= 0) {
            return 'out_of_stock';
        } elseif ($material->stok <= $material->safety_stock) {
            return 'critical';
        } elseif ($material->stok <= $rop) {
            return 'need_reorder';
        } elseif ($daysUntilStockout !== null && $daysUntilStockout <= 7) {
            return 'warning';
        } else {
            return 'safe';
        }
    }
}