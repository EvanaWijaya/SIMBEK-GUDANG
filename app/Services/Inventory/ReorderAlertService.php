<?php

namespace App\Services\Inventory;

use App\Models\Material;

/**
 * ========================================
 * REORDER ALERT SERVICE
 * ========================================
 * 
 * Responsibility: Restock notifications & alerts
 * - Get materials that need restock
 * - Priority-based alerts
 * - Supplier grouping for efficient ordering
 * 
 * @package App\Services\Inventory
 * @version 2.0 (Refactored)
 */
class ReorderAlertService
{
    protected ROPService $ropService;
    protected SafetyStockService $safetyStockService;

    public function __construct(ROPService $ropService, SafetyStockService $safetyStockService)
    {
        $this->ropService = $ropService;
        $this->safetyStockService = $safetyStockService;
    }

    /**
     * Get all materials that need restock
     * 
     * @return array
     */
    public function getMaterialsNeedRestock(): array
    {
        $materials = Material::all();
        $needRestock = [];

        foreach ($materials as $material) {
            if ($this->ropService->needsRestock($material->id)) {
                $ropDetails = $this->ropService->getROPDetails($material->id);
                
                $needRestock[] = [
                    'material_id' => $material->id,
                    'nama_material' => $material->nama_material,
                    'current_stock' => $material->stok,
                    'safety_stock' => $material->safety_stock,
                    'rop' => $ropDetails['rop'],
                    'daily_usage' => $ropDetails['daily_usage'],
                    'lead_time_days' => $material->lead_time_days,
                    'days_until_stockout' => $ropDetails['days_until_stockout'],
                    'priority' => $this->calculatePriority($material, $ropDetails),
                    'supplier' => $material->supplier,
                    'suggested_order_qty' => $this->calculateSuggestedOrderQty($material, $ropDetails),
                ];
            }
        }

        // Sort by priority
        usort($needRestock, fn($a, $b) => $b['priority'] <=> $a['priority']);

        return $needRestock;
    }

    /**
     * Get restock alerts grouped by priority
     * 
     * @return array
     */
    public function getAlertsByPriority(): array
    {
        $materials = $this->getMaterialsNeedRestock();

        $critical = array_filter($materials, fn($m) => $m['priority'] >= 90);
        $high = array_filter($materials, fn($m) => $m['priority'] >= 70 && $m['priority'] < 90);
        $medium = array_filter($materials, fn($m) => $m['priority'] >= 50 && $m['priority'] < 70);
        $low = array_filter($materials, fn($m) => $m['priority'] < 50);

        return [
            'critical' => array_values($critical),
            'high' => array_values($high),
            'medium' => array_values($medium),
            'low' => array_values($low),
            'summary' => [
                'total_alerts' => count($materials),
                'critical_count' => count($critical),
                'high_count' => count($high),
                'medium_count' => count($medium),
                'low_count' => count($low),
            ],
        ];
    }

    /**
     * Get restock alerts grouped by supplier
     * 
     * @return array
     */
    public function getAlertsBySupplier(): array
    {
        $materials = $this->getMaterialsNeedRestock();
        
        $bySupplier = [];

        foreach ($materials as $material) {
            $supplier = $material['supplier'] ?? 'Unknown';
            
            if (!isset($bySupplier[$supplier])) {
                $bySupplier[$supplier] = [
                    'supplier' => $supplier,
                    'materials' => [],
                    'total_items' => 0,
                ];
            }

            $bySupplier[$supplier]['materials'][] = $material;
            $bySupplier[$supplier]['total_items']++;
        }

        return array_values($bySupplier);
    }

    /**
     * Get alert summary for dashboard
     * 
     * @return array
     */
    public function getAlertSummary(): array
    {
        $materials = $this->getMaterialsNeedRestock();
        
        $criticalMaterials = array_filter($materials, function($m) {
            return $m['current_stock'] <= $m['safety_stock'];
        });

        $urgentMaterials = array_filter($materials, function($m) {
            return $m['days_until_stockout'] !== null && $m['days_until_stockout'] <= 3;
        });

        return [
            'total_alerts' => count($materials),
            'critical_count' => count($criticalMaterials),
            'urgent_count' => count($urgentMaterials),
            'top_5_priority' => array_slice($materials, 0, 5),
            'estimated_order_value' => $this->calculateTotalOrderValue($materials),
        ];
    }

    /**
     * Calculate priority score (0-100)
     * 
     * Based on:
     * - Current stock vs safety stock
     * - Days until stockout
     * - Daily usage rate
     */
    private function calculatePriority(Material $material, array $ropDetails): int
    {
        $priority = 0;

        // Factor 1: Stock level (40 points)
        if ($material->stok <= 0) {
            $priority += 40;
        } elseif ($material->stok <= $material->safety_stock) {
            $priority += 35;
        } elseif ($material->stok <= $ropDetails['rop'] * 0.5) {
            $priority += 30;
        } elseif ($material->stok <= $ropDetails['rop']) {
            $priority += 20;
        }

        // Factor 2: Days until stockout (40 points)
        $daysUntilStockout = $ropDetails['days_until_stockout'];
        if ($daysUntilStockout !== null) {
            if ($daysUntilStockout <= 1) {
                $priority += 40;
            } elseif ($daysUntilStockout <= 3) {
                $priority += 35;
            } elseif ($daysUntilStockout <= 7) {
                $priority += 25;
            } elseif ($daysUntilStockout <= 14) {
                $priority += 15;
            }
        }

        // Factor 3: Daily usage rate (20 points)
        if ($ropDetails['daily_usage'] > 10) {
            $priority += 20; // High usage
        } elseif ($ropDetails['daily_usage'] > 5) {
            $priority += 15; // Medium usage
        } elseif ($ropDetails['daily_usage'] > 0) {
            $priority += 10; // Low usage
        }

        return min(100, $priority);
    }

    /**
     * Calculate suggested order quantity
     * 
     * Formula: Order Qty = (Daily Usage × Lead Time × 2) + Safety Stock - Current Stock
     * 
     * @param Material $material
     * @param array $ropDetails
     * @return float
     */
    private function calculateSuggestedOrderQty(Material $material, array $ropDetails): float
    {
        $dailyUsage = $ropDetails['daily_usage'];
        $leadTime = $material->lead_time_days;
        
        // Order enough for 2x lead time + safety stock
        $targetStock = ($dailyUsage * $leadTime * 2) + $material->safety_stock;
        $orderQty = max(0, $targetStock - $material->stok);

        return round($orderQty, 2);
    }

    /**
     * Calculate total estimated order value
     */
    private function calculateTotalOrderValue(array $materials): float
    {
        $totalValue = 0;

        foreach ($materials as $mat) {
            $material = Material::find($mat['material_id']);
            if ($material) {
                $totalValue += $mat['suggested_order_qty'] * $material->harga;
            }
        }

        return round($totalValue, 2);
    }
}