<?php

namespace App\Services;

use App\Models\Formula;
use Exception;

/**
 * ========================================
 * PRODUCTION PLANNING SERVICE
 * ========================================
 * 
 * Handle production planning & simulation
 * Responsibilities: Simulation, Optimal qty calculation, Recommendations
 * 
 * Author: SIMBEK Team
 * Version: 2.0
 */
class ProductionPlanningService
{
    protected FormulaCostService $costService;
    protected FormulaAnalysisService $analysisService;

    public function __construct(
        FormulaCostService $costService,
        FormulaAnalysisService $analysisService
    ) {
        $this->costService = $costService;
        $this->analysisService = $analysisService;
    }

    /**
     * Simulasi produksi (tanpa execute)
     * Untuk planning & decision making
     */
    public function simulateProduction(int $formulaId, float $qty): array
    {
        // Validate stock
        $validation = $this->costService->validateStockForProduction($formulaId, $qty);
        
        // Calculate cost & revenue
        $costAnalysis = $this->costService->calculateProductionCost($formulaId, $qty);
        
        // Get formula efficiency
        $efficiency = $this->analysisService->getFormulaEfficiency($formulaId);

        $formula = Formula::with('product')->findOrFail($formulaId);

        return [
            'formula' => [
                'id' => $formula->id,
                'name' => $formula->nama_formula,
                'product' => $formula->product->nama_produk,
            ],
            'planned_qty' => $qty,
            'can_produce' => $validation['is_sufficient'],
            'stock_validation' => $validation,
            'cost_analysis' => $costAnalysis,
            'efficiency_score' => $efficiency['scores']['overall_efficiency'],
            'recommendation' => $this->generateRecommendation($validation, $costAnalysis, $efficiency),
        ];
    }

    /**
     * Get optimal production quantity
     * Berdasarkan: available stock, demand, ROP
     */
    public function getOptimalProductionQty(int $formulaId): array
    {
        $formula = Formula::with('details.material')->findOrFail($formulaId);
        
        // Cari material yang paling limiting (bottleneck)
        $maxProducibleByMaterial = [];

        foreach ($formula->details as $detail) {
            $availableStock = $detail->material->stok;
            $requiredPerKg = $detail->qty;
            
            // Max qty yang bisa diproduksi berdasarkan material ini
            $maxQty = $requiredPerKg > 0 ? floor($availableStock / $requiredPerKg) : 0;
            
            $maxProducibleByMaterial[] = [
                'material' => $detail->material->nama_material,
                'max_qty' => $maxQty,
                'is_bottleneck' => false,
            ];
        }

        // Sort untuk cari bottleneck
        usort($maxProducibleByMaterial, fn($a, $b) => $a['max_qty'] <=> $b['max_qty']);
        
        // Material pertama (qty terkecil) adalah bottleneck
        if (!empty($maxProducibleByMaterial)) {
            $maxProducibleByMaterial[0]['is_bottleneck'] = true;
        }

        $optimalQty = $maxProducibleByMaterial[0]['max_qty'] ?? 0;

        return [
            'formula_id' => $formulaId,
            'formula_name' => $formula->nama_formula,
            'optimal_qty' => $optimalQty,
            'unit' => 'kg',
            'limiting_factors' => $maxProducibleByMaterial,
            'recommendation' => $optimalQty > 0 
                ? "Dapat memproduksi maksimal {$optimalQty} kg berdasarkan stok material yang tersedia."
                : "Tidak dapat memproduksi. Lakukan restock material terlebih dahulu.",
        ];
    }

    /**
     * Generate recommendation based on simulation
     */
    private function generateRecommendation(array $validation, array $costAnalysis, array $efficiency): string
    {
        if (!$validation['is_sufficient']) {
            return 'Tidak disarankan: Stok material tidak mencukupi. Lakukan restock terlebih dahulu.';
        }

        $margin = $costAnalysis['revenue_analysis']['margin_percent'];
        $efficiencyScore = $efficiency['scores']['overall_efficiency'];

        if ($margin < 20) {
            return 'Perhatian: Margin terlalu rendah (< 20%). Pertimbangkan untuk menaikkan harga jual atau cari supplier yang lebih murah.';
        }

        if ($efficiencyScore < 60) {
            return 'Perhatian: Efficiency score rendah. Pertimbangkan menggunakan formula alternatif yang lebih efisien.';
        }

        return 'Rekomendasi: Produksi dapat dilanjutkan. Semua parameter dalam kondisi baik.';
    }
}