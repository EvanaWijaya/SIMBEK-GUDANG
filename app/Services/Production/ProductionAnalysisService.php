<?php

namespace App\Services;

use App\Models\Production;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * ========================================
 * PRODUCTION ANALYSIS SERVICE
 * ========================================
 * 
 * Handle production analysis & reporting
 * Responsibilities: Summaries, Efficiency analysis, Performance metrics
 * 
 * Author: SIMBEK Team
 * Version: 2.0
 */
class ProductionAnalysisService
{
    protected FormulaCostService $costService;

    public function __construct(FormulaCostService $costService)
    {
        $this->costService = $costService;
    }

    /**
     * Get production summary untuk periode tertentu
     */
    public function getProductionSummary(string $startDate, string $endDate): array
    {
        $productions = Production::whereBetween('tgl_produksi', [$startDate, $endDate])
            ->with(['product', 'formula'])
            ->get();

        $summary = [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'total_productions' => $productions->count(),
            'by_status' => [
                'selesai' => $productions->where('status', 'selesai')->count(),
                'pending' => $productions->where('status', 'pending')->count(),
                'batal' => $productions->where('status', 'batal')->count(),
            ],
            'total_qty_produced' => $productions->where('status', 'selesai')->sum('jumlah'),
            'by_product' => [],
            'total_cost' => 0,
        ];

        // Group by product
        $byProduct = $productions->where('status', 'selesai')->groupBy('product_id');

        foreach ($byProduct as $productId => $productProductions) {
            $product = $productProductions->first()->product;
            $totalQty = $productProductions->sum('jumlah');

            // Calculate total cost untuk produksi ini
            $totalProductCost = 0;
            foreach ($productProductions as $prod) {
                $costAnalysis = $this->costService->calculateProductionCost(
                    $prod->formula_id,
                    $prod->jumlah
                );
                $totalProductCost += $costAnalysis['cost_breakdown']['total_material_cost'];
            }

            $summary['by_product'][] = [
                'product_id' => $productId,
                'product_name' => $product->nama_produk,
                'total_qty' => $totalQty,
                'production_count' => $productProductions->count(),
                'total_cost' => $totalProductCost,
            ];

            $summary['total_cost'] += $totalProductCost;
        }

        return $summary;
    }

    /**
     * Get production efficiency
     * Membandingkan actual vs planned
     */
    public function getProductionEfficiency(int $productionId): array
    {
        $production = Production::with(['product', 'formula.details.material'])->findOrFail($productionId);

        if ($production->status !== 'selesai') {
            throw new Exception("Efficiency hanya bisa dihitung untuk produksi yang selesai");
        }

        // Calculate planned cost
        $costAnalysis = $this->costService->calculateProductionCost(
            (int) $production->formula_id,
            (float) ($production->jumlah ?? 0)
        );


        // Calculate actual material usage (dari stock movements)
        $actualMaterialUsage = DB::table('stock_movements')
            ->where('sumber', 'production')
            ->where('ref_id', $production->id)
            ->where('tipe', 'keluar')
            ->whereNotNull('material_id')
            ->join('materials', 'materials.id', '=', 'stock_movements.material_id')
            ->select('materials.nama_material', 'stock_movements.qty', 'materials.harga')
            ->get();

        $actualCost = $actualMaterialUsage->sum(function ($item) {
            return $item->qty * $item->harga;
        });

        $plannedCost = $costAnalysis['cost_breakdown']['total_material_cost'];
        $variance = $actualCost - $plannedCost;
        $efficiencyPercent = $plannedCost > 0
            ? (($plannedCost - abs($variance)) / $plannedCost) * 100
            : 100;

        return [
            'production_id' => $productionId,
            'product_name' => $production->product->nama_produk,
            'qty_produced' => $production->jumlah,
            'cost_analysis' => [
                'planned_cost' => $plannedCost,
                'actual_cost' => $actualCost,
                'variance' => $variance,
                'variance_percent' => $plannedCost > 0 ? ($variance / $plannedCost) * 100 : 0,
            ],
            'efficiency_percent' => max(0, min(100, $efficiencyPercent)),
            'status' => $variance <= 0 ? 'efficient' : 'over_budget',
            'material_usage' => $actualMaterialUsage->map(function ($item) {
                return [
                    'material' => $item->nama_material,
                    'qty_used' => $item->qty,
                    'cost' => $item->qty * $item->harga,
                ];
            })->toArray(),
        ];
    }
}