<?php

namespace App\Services;

use App\Models\Formula;
use App\Models\Product;
use Exception;

/**
 * ========================================
 * FORMULA COST SERVICE
 * ========================================
 * 
 * Handle perhitungan biaya dan analisis finansial formula
 * Responsibilities: Material needs, Cost calculation, Stock validation
 * 
 * Author: SIMBEK Team
 * Version: 2.0
 */
class FormulaCostService
{
    /**
     * Calculate material needs untuk qty produksi tertentu
     */
    public function calculateMaterialNeeds(int $formulaId, float $productionQty): array
    {
        $formula = Formula::with('details.material')->findOrFail($formulaId);

        if (!$formula->is_active) {
            throw new Exception("Formula '{$formula->nama_formula}' tidak aktif");
        }

        $needs = [];
        $totalCost = 0;

        foreach ($formula->details as $detail) {
            $neededQty = $detail->qty * $productionQty;
            $cost = $neededQty * $detail->material->harga;

            $needs[] = [
                'material_id' => $detail->material_id,
                'nama_material' => $detail->material->nama_material,
                'formula_qty_per_kg' => $detail->qty,
                'needed_qty' => $neededQty,
                'available_stock' => $detail->material->stok,
                'unit_price' => $detail->material->harga,
                'total_cost' => $cost,
                'is_sufficient' => $detail->material->stok >= $neededQty,
                'shortage' => max(0, $neededQty - $detail->material->stok),
            ];

            $totalCost += $cost;
        }

        return [
            'formula_id' => $formula->id,
            'formula_name' => $formula->nama_formula,
            'product_id' => $formula->product_id,
            'product_name' => $formula->product->nama_produk,
            'production_qty' => $productionQty,
            'materials' => $needs,
            'total_cost' => round($totalCost, 2),
            'cost_per_kg' => round($totalCost / $productionQty, 2),
            'all_materials_sufficient' => !in_array(false, array_column($needs, 'is_sufficient')),
        ];
    }

    /**
     * Validasi apakah stok mencukupi untuk produksi
     */
    public function validateStockForProduction(int $formulaId, float $productionQty): array
    {
        $calculation = $this->calculateMaterialNeeds($formulaId, $productionQty);

        $insufficientMaterials = array_filter(
            $calculation['materials'], 
            fn($m) => !$m['is_sufficient']
        );

        return [
            'is_sufficient' => $calculation['all_materials_sufficient'],
            'production_qty' => $productionQty,
            'total_cost' => $calculation['total_cost'],
            'materials_needed' => $calculation['materials'],
            'insufficient_materials' => array_values($insufficientMaterials),
            'can_proceed' => $calculation['all_materials_sufficient'],
        ];
    }

    /**
     * Hitung biaya produksi untuk formula
     */
    public function calculateProductionCost(int $formulaId, float $productionQty): array
    {
        $calculation = $this->calculateMaterialNeeds($formulaId, $productionQty);
        $product = Product::findOrFail($calculation['product_id']);

        $totalRevenue = $productionQty * $product->harga_jual;
        $grossProfit = $totalRevenue - $calculation['total_cost'];
        $marginPercent = $calculation['total_cost'] > 0 
            ? ($grossProfit / $calculation['total_cost']) * 100 
            : 0;

        return [
            'formula_name' => $calculation['formula_name'],
            'product_name' => $product->nama_produk,
            'production_qty' => $productionQty,
            'cost_breakdown' => [
                'materials' => array_map(function($m) {
                    return [
                        'nama_material' => $m['nama_material'],
                        'qty' => $m['needed_qty'],
                        'unit_price' => $m['unit_price'],
                        'total_cost' => $m['total_cost'],
                    ];
                }, $calculation['materials']),
                'total_material_cost' => $calculation['total_cost'],
                'cost_per_kg' => $calculation['cost_per_kg'],
            ],
            'revenue_analysis' => [
                'selling_price_per_kg' => $product->harga_jual,
                'total_revenue' => $totalRevenue,
                'gross_profit' => $grossProfit,
                'margin_percent' => round($marginPercent, 2),
            ],
        ];
    }
}