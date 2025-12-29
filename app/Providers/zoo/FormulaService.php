<?php

namespace App\Services;

use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\Product;
use App\Models\Material;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * ========================================
 * FORMULA SERVICE - SIMBEK INVENTORY SYSTEM
 * ========================================
 * 
 * Service untuk manajemen formula/resep produksi
 * Handle: Create, Update, Calculate needs, Validation
 * 
 * Author: SIMBEK Team
 * Version: 1.0
 */
class FormulaService
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * ========================================
     * FORMULA CRUD OPERATIONS
     * ========================================
     */

    /**
     * Create formula baru dengan details
     * 
     * @param array $data [
     *   'product_id' => int,
     *   'nama_formula' => string,
     *   'catatan' => string,
     *   'materials' => [
     *     ['material_id' => int, 'qty' => float],
     *     ...
     *   ]
     * ]
     * @return Formula
     */
    public function createFormula(array $data): Formula
    {
        // Validasi product exists
        $product = Product::findOrFail($data['product_id']);

        // Validasi total qty = 1 kg (standar)
        $totalQty = array_sum(array_column($data['materials'], 'qty'));
        if (abs($totalQty - 1.0) > 0.01) { // Toleransi 0.01 untuk floating point
            throw new Exception(
                "Total komposisi formula harus = 1 kg. Current total: {$totalQty} kg"
            );
        }

        return DB::transaction(function () use ($data, $product) {
            // Create formula
            $formula = Formula::create([
                'product_id' => $data['product_id'],
                'nama_formula' => $data['nama_formula'],
                'catatan' => $data['catatan'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // Create formula details
            foreach ($data['materials'] as $materialData) {
                // Validasi material exists
                $material = Material::findOrFail($materialData['material_id']);

                FormulaDetail::create([
                    'formula_id' => $formula->id,
                    'material_id' => $materialData['material_id'],
                    'qty' => $materialData['qty'],
                    'satuan' => 'kg', // Konsisten pakai kg
                ]);
            }

            return $formula->load('details.material');
        });
    }

    /**
     * Update formula existing
     */
    public function updateFormula(int $formulaId, array $data): Formula
    {
        $formula = Formula::findOrFail($formulaId);

        // Validasi total qty kalau ada update materials
        if (isset($data['materials'])) {
            $totalQty = array_sum(array_column($data['materials'], 'qty'));
            if (abs($totalQty - 1.0) > 0.01) {
                throw new Exception(
                    "Total komposisi formula harus = 1 kg. Current total: {$totalQty} kg"
                );
            }
        }

        return DB::transaction(function () use ($formula, $data) {
            // Update formula info
            $formula->update([
                'nama_formula' => $data['nama_formula'] ?? $formula->nama_formula,
                'catatan' => $data['catatan'] ?? $formula->catatan,
                'is_active' => $data['is_active'] ?? $formula->is_active,
            ]);

            // Update materials kalau ada
            if (isset($data['materials'])) {
                // Delete existing details
                $formula->details()->delete();

                // Create new details
                foreach ($data['materials'] as $materialData) {
                    Material::findOrFail($materialData['material_id']); // Validate exists

                    FormulaDetail::create([
                        'formula_id' => $formula->id,
                        'material_id' => $materialData['material_id'],
                        'qty' => $materialData['qty'],
                        'satuan' => 'kg',
                    ]);
                }
            }

            return $formula->fresh(['details.material']);
        });
    }

    /**
     * Delete formula
     * Only allowed jika tidak ada production yang menggunakan
     */
    public function deleteFormula(int $formulaId): bool
    {
        $formula = Formula::findOrFail($formulaId);

        // Check apakah ada production yang pakai formula ini
        if ($formula->productions()->exists()) {
            throw new Exception(
                "Formula tidak dapat dihapus karena sudah digunakan di produksi. " .
                "Nonaktifkan saja jika tidak ingin dipakai lagi."
            );
        }

        return DB::transaction(function () use ($formula) {
            // Delete details first (cascade should handle this, but explicit is better)
            $formula->details()->delete();
            
            // Delete formula
            return $formula->delete();
        });
    }

    /**
     * Toggle active status
     */
    public function toggleActive(int $formulaId): Formula
    {
        $formula = Formula::findOrFail($formulaId);
        $formula->update(['is_active' => !$formula->is_active]);
        
        return $formula;
    }

    /**
     * ========================================
     * MATERIAL NEEDS CALCULATION
     * ========================================
     * 
     * Hitung kebutuhan material untuk produksi
     * Formula: Material Needed = Formula Qty × Production Qty
     */

    /**
     * Calculate material needs untuk qty produksi tertentu
     * 
     * @param int $formulaId
     * @param float $productionQty (dalam kg)
     * @return array
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
                'formula_qty_per_kg' => $detail->qty, // Kebutuhan per 1 kg produk
                'needed_qty' => $neededQty, // Total kebutuhan
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
     * ========================================
     * STOCK VALIDATION FOR PRODUCTION
     * ========================================
     */

    /**
     * Validasi apakah stok mencukupi untuk produksi
     * 
     * @return array ['is_sufficient' => bool, 'details' => [...]]
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
     * ========================================
     * PRODUCTION COST CALCULATION
     * ========================================
     * 
     * Formula: Total Cost = Σ(Material Qty × Material Price)
     */

    /**
     * Hitung biaya produksi untuk formula
     * 
     * @return array
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

    /**
     * ========================================
     * FORMULA ANALYSIS
     * ========================================
     */

    /**
     * Get formula efficiency score
     * Berdasarkan: cost, margin, availability
     */
    public function getFormulaEfficiency(int $formulaId): array
    {
        $formula = Formula::with(['details.material', 'product'])->findOrFail($formulaId);
        
        // Calculate untuk 100 kg production (standar)
        $calculation = $this->calculateMaterialNeeds($formulaId, 100);
        $cost = $this->calculateProductionCost($formulaId, 100);

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
            $cost = $this->calculateProductionCost($formula->id, $productionQty);

            $comparisons[] = [
                'formula_id' => $formula->id,
                'formula_name' => $formula->nama_formula,
                'is_active' => $formula->is_active,
                'efficiency_score' => $efficiency['scores']['overall_efficiency'],
                'cost_per_kg' => $cost['cost_breakdown']['cost_per_kg'],
                'margin_percent' => $cost['revenue_analysis']['margin_percent'],
                'can_produce_now' => $this->validateStockForProduction($formula->id, $productionQty)['is_sufficient'],
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