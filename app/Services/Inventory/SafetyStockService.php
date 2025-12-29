<?php

namespace App\Services\Inventory;

use App\Models\Material;
use App\Models\StockMovement;

/**
 * ========================================
 * SAFETY STOCK SERVICE
 * ========================================
 * 
 * Responsibility: Safety Stock calculation & management
 * - Manual safety stock (Phase 1)
 * - Auto-calculate safety stock (Phase 2+)
 * - Adaptive recommendations
 * - Delay buffer calculation
 * 
 * Formula: Safety Stock = Z-Score × σ × √Lead Time
 * 
 * Z-Score based on service level:
 * - 90% = 1.28
 * - 95% = 1.65
 * - 99% = 2.33
 * 
 * @package App\Services\Inventory
 * @version 2.0 (Refactored)
 */
class SafetyStockService
{
    protected ROPService $ropService;

    public function __construct(ROPService $ropService)
    {
        $this->ropService = $ropService;
    }

    /**
     * Check if material has enough data for auto-calculation
     * 
     * Minimum: 30 days data with 20+ transactions
     * 
     * @param int $materialId
     * @return bool
     */
    public function hasEnoughData(int $materialId): bool
    {
        $thirtyDaysAgo = now()->subDays(30);
        
        $transactionCount = StockMovement::where('material_id', $materialId)
            ->where('tipe', 'keluar')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        return $transactionCount >= 20;
    }

    /**
     * Calculate auto safety stock
     * 
     * Formula: Safety Stock = Z-Score × σ × √Lead Time
     * 
     * @param int $materialId
     * @param float $serviceLevel (0.95 = 95%)
     * @return float
     */
    public function calculateAuto(int $materialId, float $serviceLevel = 0.95): float
    {
        $material = Material::findOrFail($materialId);
        $dailyUsages = $this->getDailyUsageHistory($materialId, 60);

        // Fallback if not enough data
        if (count($dailyUsages) < 30) {
            return $material->stok_min * 0.5;
        }

        $stdDev = $this->calculateStandardDeviation($dailyUsages);
        $zScore = $this->getZScore($serviceLevel);

        $safetyStock = $zScore * $stdDev * sqrt($material->lead_time_days);

        return round($safetyStock, 2);
    }

    /**
     * Get safety stock recommendation
     * 
     * @param int $materialId
     * @return array
     */
    public function getRecommendation(int $materialId): array
    {
        $material = Material::findOrFail($materialId);

        if (!$this->hasEnoughData($materialId)) {
            return [
                'material_id' => $materialId,
                'material_name' => $material->nama_material,
                'current_safety_stock' => $material->safety_stock,
                'recommended_safety_stock' => null,
                'status' => 'insufficient_data',
                'message' => 'Data historis belum cukup (minimal 30 hari dengan 20+ transaksi)',
            ];
        }

        $recommended = $this->calculateAuto($materialId, 0.95);
        $variance = $recommended - $material->safety_stock;
        $variancePercent = $material->safety_stock > 0 
            ? ($variance / $material->safety_stock) * 100 
            : 0;

        return [
            'material_id' => $materialId,
            'material_name' => $material->nama_material,
            'current_safety_stock' => $material->safety_stock,
            'recommended_safety_stock' => $recommended,
            'variance' => round($variance, 2),
            'variance_percent' => round($variancePercent, 2),
            'status' => 'ready',
            'action' => $this->determineAction($variance, $variancePercent),
            'daily_usage_avg' => $this->ropService->calculateDailyUsage($materialId),
        ];
    }

    /**
     * Calculate delay buffer
     * 
     * Formula: Delay Buffer = Daily Usage × Avg Delay Days
     * 
     * @param int $materialId
     * @param float $avgDelayDays
     * @return float
     */
    public function calculateDelayBuffer(int $materialId, float $avgDelayDays = 2): float
    {
        $dailyUsage = $this->ropService->calculateDailyUsage($materialId);
        return round($dailyUsage * $avgDelayDays, 2);
    }

    /**
     * Get complete recommendation (Safety Stock + Delay Buffer)
     * 
     * @param int $materialId
     * @param float $avgDelayDays
     * @return array
     */
    public function getCompleteRecommendation(int $materialId, float $avgDelayDays = 2): array
    {
        $material = Material::findOrFail($materialId);
        $recommendation = $this->getRecommendation($materialId);
        $delayBuffer = $this->calculateDelayBuffer($materialId, $avgDelayDays);
        $dailyUsage = $this->ropService->calculateDailyUsage($materialId);

        $totalRecommended = ($recommendation['recommended_safety_stock'] ?? $material->safety_stock) + $delayBuffer;

        return [
            'material_id' => $materialId,
            'material_name' => $material->nama_material,
            'current_setup' => [
                'safety_stock' => $material->safety_stock,
                'lead_time_days' => $material->lead_time_days,
            ],
            'recommendation' => [
                'base_safety_stock' => $recommendation['recommended_safety_stock'] ?? $material->safety_stock,
                'delay_buffer' => $delayBuffer,
                'total_safety_stock' => $totalRecommended,
            ],
            'analysis' => [
                'daily_usage' => $dailyUsage,
                'avg_delay_days' => $avgDelayDays,
                'data_sufficient' => $this->hasEnoughData($materialId),
            ],
        ];
    }

    /**
     * Get daily usage history
     */
    private function getDailyUsageHistory(int $materialId, int $days): array
    {
        $dailyUsages = [];

        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            
            $usage = StockMovement::where('material_id', $materialId)
                ->where('tipe', 'keluar')
                ->whereDate('created_at', $date)
                ->sum('qty');

            if ($usage > 0) {
                $dailyUsages[] = $usage;
            }
        }

        return $dailyUsages;
    }

    /**
     * Calculate standard deviation
     * 
     * Formula: σ = √(Σ(xi - x̄)² / (n-1))
     */
    private function calculateStandardDeviation(array $data): float
    {
        if (count($data) < 2) return 0;

        $mean = array_sum($data) / count($data);
        $variance = 0;

        foreach ($data as $value) {
            $variance += pow($value - $mean, 2);
        }

        $variance = $variance / (count($data) - 1);
        return sqrt($variance);
    }

    /**
     * Get Z-Score based on service level
     */
    private function getZScore(float $serviceLevel): float
    {
        $zScores = [
            0.90 => 1.28,
            0.95 => 1.65,
            0.97 => 1.88,
            0.99 => 2.33,
            0.995 => 2.58,
        ];

        return $zScores[$serviceLevel] ?? 1.65;
    }

    /**
     * Determine action based on variance
     */
    private function determineAction(float $variance, float $variancePercent): string
    {
        if (abs($variancePercent) < 10) {
            return 'ok';
        } elseif ($variance > 0 && $variancePercent > 20) {
            return 'increase_critical';
        } elseif ($variance > 0) {
            return 'increase_recommended';
        } elseif ($variance < 0 && $variancePercent < -20) {
            return 'decrease_recommended';
        }
        return 'review';
    }
}