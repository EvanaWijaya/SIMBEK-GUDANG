<?php

namespace App\Services;

use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\Material;
use App\Services\Stock\ProductPlanningService;
use App\Services\Stock\MaterialPlanningService;
use Exception;

/**
 * ========================================
 * PRODUCTION PLANNING SERVICE
 * ========================================
 * Handle production planning, simulation,
 * dan ROP-aware production readiness
 */
class ProductionPlanningService
{
    protected FormulaCostService $costService;
    protected FormulaAnalysisService $analysisService;
    protected ProductPlanningService $productPlanning;
    protected MaterialPlanningService $materialPlanning;

    public function __construct(
        FormulaCostService $costService,
        FormulaAnalysisService $analysisService,
        ProductPlanningService $productPlanning,
        MaterialPlanningService $materialPlanning
    ) {
        $this->costService       = $costService;
        $this->analysisService   = $analysisService;
        $this->productPlanning  = $productPlanning;
        $this->materialPlanning = $materialPlanning;
    }

    /**
     * ===============================
     * SIMULASI PRODUKSI (EXISTING)
     * ===============================
     */
    public function simulateProduction(int $formulaId, float $qty): array
    {
        $validation   = $this->costService->validateStockForProduction($formulaId, $qty);
        $costAnalysis = $this->costService->calculateProductionCost($formulaId, $qty);
        $efficiency   = $this->analysisService->getFormulaEfficiency($formulaId);

        $formula = Formula::with('product')->findOrFail($formulaId);

        return [
            'formula' => [
                'id'      => $formula->id,
                'name'    => $formula->nama_formula,
                'product' => $formula->product->nama_produk,
            ],
            'planned_qty'       => $qty,
            'can_produce'       => $validation['is_sufficient'],
            'stock_validation'  => $validation,
            'cost_analysis'     => $costAnalysis,
            'efficiency_score'  => $efficiency['scores']['overall_efficiency'],
            'recommendation'    => $this->generateRecommendation(
                $validation,
                $costAnalysis,
                $efficiency
            ),
        ];
    }

    /**
     * ======================================
     * ANALISIS PRODUKSI BERBASIS ROP (BARU)
     * ======================================
     */
    public function analyzeProductionWithROP(int $formulaId): array
    {
        $formula = Formula::with('product')->findOrFail($formulaId);
        $productId = $formula->product_id;

        // 1. Cek apakah produk perlu diproduksi
        $needsProduction = $this->productPlanning->needsProduction($productId);

        if (!$needsProduction) {
            return [
                'needs_production' => false,
                'message' => 'Stok produk masih di atas ROP',
            ];
        }

        // 2. Analisis material berdasarkan ROP
        $materialStatus = [];

        foreach ($formula->details as $detail) {
            $material = Material::find($detail->material_id);

            if (!$material) {
                continue;
            }

            $rop = $this->materialPlanning->calculateROP($material);

            $materialStatus[] = [
                'material_id'   => $material->id,
                'nama_material' => $material->nama_material,
                'stok'          => (float) $material->stok,
                'rop'           => $rop,
                'needs_restock' => $material->stok <= $rop,
            ];
        }

        return [
            'needs_production' => true,
            'product' => [
                'id'   => $productId,
                'name' => $formula->product->nama_produk,
            ],
            'materials' => $materialStatus,
        ];
    }

    /**
     * ======================================
     * OPTIMAL PRODUCTION QTY (EXISTING)
     * ======================================
     */
    public function getOptimalProductionQty(int $formulaId): array
    {
        $formula = Formula::with('details.material')->findOrFail($formulaId);

        $maxProducibleByMaterial = [];

        foreach ($formula->details as $detail) {
            $availableStock = $detail->material->stok;
            $requiredPerKg  = $detail->qty;

            $maxQty = $requiredPerKg > 0
                ? floor($availableStock / $requiredPerKg)
                : 0;

            $maxProducibleByMaterial[] = [
                'material'      => $detail->material->nama_material,
                'max_qty'       => $maxQty,
                'is_bottleneck' => false,
            ];
        }

        usort($maxProducibleByMaterial, fn($a, $b) => $a['max_qty'] <=> $b['max_qty']);

        if (!empty($maxProducibleByMaterial)) {
            $maxProducibleByMaterial[0]['is_bottleneck'] = true;
        }

        $optimalQty = $maxProducibleByMaterial[0]['max_qty'] ?? 0;

        return [
            'formula_id'        => $formulaId,
            'formula_name'      => $formula->nama_formula,
            'optimal_qty'       => $optimalQty,
            'unit'              => 'kg',
            'limiting_factors'  => $maxProducibleByMaterial,
            'recommendation'    => $optimalQty > 0
                ? "Dapat memproduksi maksimal {$optimalQty} kg berdasarkan stok material."
                : "Tidak dapat memproduksi. Lakukan restock material terlebih dahulu.",
        ];
    }

    /**
     * Recommendation helper
     */
    private function generateRecommendation(
        array $validation,
        array $costAnalysis,
        array $efficiency
    ): string {
        if (!$validation['is_sufficient']) {
            return 'Tidak disarankan: Stok material tidak mencukupi.';
        }

        $margin = $costAnalysis['revenue_analysis']['margin_percent'];
        $efficiencyScore = $efficiency['scores']['overall_efficiency'];

        if ($margin < 20) {
            return 'Margin rendah (<20%). Pertimbangkan penyesuaian harga atau supplier.';
        }

        if ($efficiencyScore < 60) {
            return 'Efficiency score rendah. Pertimbangkan formula alternatif.';
        }

        return 'Produksi direkomendasikan. Semua parameter aman.';
    }
}
