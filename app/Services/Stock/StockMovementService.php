<?php

namespace App\Services\Stock;

use App\Models\StockMovement;

/**
 * ========================================
 * STOCK MOVEMENT SERVICE
 * ========================================
 * 
 * Responsibility: Stock movement tracking ONLY
 * - Record all stock movements (in/out)
 * - Query movements by various filters
 * 
 * All stock changes MUST go through this service
 * for proper audit trail
 * 
 * @package App\Services\Stock
 * @version 2.0 (Refactored)
 */
class StockMovementService
{
    /**
     * Record stock movement
     * 
     * @param array $data [
     *   'tipe' => 'masuk'|'keluar',
     *   'sumber' => string,
     *   'qty' => float,
     *   'product_stock_id' => int|null,
     *   'material_id' => int|null,
     *   'ref_id' => int|null,
     *   'notes' => string|null
     * ]
     * @return StockMovement
     */
    public function record(array $data): StockMovement
    {
        return StockMovement::create($data);
    }

    /**
     * Get movements by material
     * 
     * @param int $materialId
     * @param string|null $tipe (masuk/keluar)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByMaterial(int $materialId, ?string $tipe = null)
    {
        $query = StockMovement::where('material_id', $materialId);
        
        if ($tipe) {
            $query->where('tipe', $tipe);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get movements by product
     * 
     * @param int $productId
     * @param string|null $tipe
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByProduct(int $productId, ?string $tipe = null)
    {
        $query = StockMovement::whereHas('productStock', function($q) use ($productId) {
            $q->where('product_id', $productId);
        });
        
        if ($tipe) {
            $query->where('tipe', $tipe);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get movements by date range
     * 
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByDateRange(string $startDate, string $endDate)
    {
        return StockMovement::whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get today's movements
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getToday()
    {
        return StockMovement::whereDate('created_at', today())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get summary by type for date range
     * 
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getSummaryByType(string $startDate, string $endDate): array
    {
        $movements = $this->getByDateRange($startDate, $endDate);

        return [
            'total_movements' => $movements->count(),
            'total_masuk' => $movements->where('tipe', 'masuk')->sum('qty'),
            'total_keluar' => $movements->where('tipe', 'keluar')->sum('qty'),
            'by_source' => $movements->groupBy('sumber')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'total_qty' => $group->sum('qty'),
                ];
            })->toArray(),
        ];
    }
}