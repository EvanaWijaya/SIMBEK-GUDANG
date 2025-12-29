<?php

namespace App\Services;

use App\Models\Formula;
use Exception;

/**
 * ========================================
 * FORMULA ANALYSIS SERVICE
 * ========================================
 * 
 * Handle analisis dan perbandingan formula
 * Responsibilities: Efficiency scoring, Formula comparison
 * 
 * Author: SIMBEK Team
 * Version: 2.0
 */
class FormulaAnalysisService
{
    protected FormulaCostService $costService;

    public function __construct(FormulaCostService $costService)
    {
        $this->costService = $costService;
    }

    /**
     * Get formula efficiency score
     * Berdasarkan: cost, margin, availability
     */
    public function getFormulaEfficiency(int $formulaId): array
    {
        $formula = Formula::with(['details.material', 'product'])->findOrFail($formulaId);
        
        // Calculate untuk 100 kg production (standar)
        $calculation = $this->costService->calculateMaterialNeeds($formulaId, 100);
        $cost = $this->costService->calculateProductionCost($formulaId, 100);

        // Score factors (0-100)
        $availabilityScore = 0;
        $availableMaterials = 0;
        foreach ($calculation['materials'] as $material) {
            if ($material['is_sufficient']) {
                $availableMaterials++;
            }
        }
        $availabilityScore = count($calculation['materials']) > 0 
            ? ($availableMaterials / count($calculation['materials'])) * 100 
            : 0;

        $marginScore = min(100, max(0, $cost['revenue_analysis']['margin_percent']));

        // Overall efficiency (weighted average)
        $efficiency = (
            ($availabilityScore * 0.4) + // 40% weight
            ($marginScore * 0.6)          // 60% weight
        );

        return [
            'formula_id' => $formulaId,
            'formula_name' => $formula->nama_formula,
            'product_name' => $formula->product->nama_produk,
            'is_active' => $formula->is_active,
            'scores' => [
                'availability' => round($availabilityScore, 2),
                'margin' => round($marginScore, 2),
                'overall_efficiency' => round($efficiency, 2),
            ],
            'cost_analysis' => [
                'cost_per_kg' => $calculation['cost_per_kg'],
                'margin_percent' => $cost['revenue_analysis']['margin_percent'],
            ],
            'material_availability' => [
                'total_materials' => count($calculation['materials']),
                'available_materials' => $availableMaterials,
                'insufficient_materials' => count($calculation['materials']) - $availableMaterials,
            ],
        ];
    }

    /**
     * Compare multiple formulas untuk produk yang sama
     */
    public function compareFormulas(int $productId, float $productionQty = 100): array
    {
        $formulas = Formula::where('product_id', $productId)
            ->with('details.material')
            ->get();

        if ($formulas->isEmpty()) {
            throw new Exception("Tidak ada formula untuk produk ini");
        }

        $comparisons = [];

        foreach ($formulas as $formula) {
            $efficiency = $this->getFormulaEfficiency($formula->id);
            $cost = $this->costService->calculateProductionCost($formula->id, $productionQty);

            $comparisons[] = [
                'formula_id' => $formula->id,
                'formula_name' => $formula->nama_formula,
                'is_active' => $formula->is_active,
                'efficiency_score' => $efficiency['scores']['overall_efficiency'],
                'cost_per_kg' => $cost['cost_breakdown']['cost_per_kg'],
                'margin_percent' => $cost['revenue_analysis']['margin_percent'],
                'can_produce_now' => $this->costService->validateStockForProduction($formula->id, $productionQty)['is_sufficient'],
            ];
        }

        // Sort by efficiency score
        usort($comparisons, fn($a, $b) => $b['efficiency_score'] <=> $a['efficiency_score']);

        return [
            'product_id' => $productId,
            'production_qty' => $productionQty,
            'formulas' => $comparisons,
            'best_formula' => $comparisons[0] ?? null,
        ];
    }
}