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
 * FORMULA MANAGEMENT SERVICE
 * ========================================
 * 
 * Handle CRUD operations untuk formula
 * Responsibilities: Create, Update, Delete, Toggle Status
 * 
 * Author: SIMBEK Team
 * Version: 2.0
 */
class FormulaManagementService
{
    /**
     * Create formula baru dengan details
     */
    public function createFormula(array $data): Formula
    {
        // Validasi product exists
        $product = Product::findOrFail($data['product_id']);

        // Validasi total qty = 1 kg (standar)
        $totalQty = array_sum(array_column($data['materials'], 'qty'));
        if (abs($totalQty - 1.0) > 0.01) {
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
                    'satuan' => 'kg',
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
                    Material::findOrFail($materialData['material_id']);

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
            // Delete details first
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
}