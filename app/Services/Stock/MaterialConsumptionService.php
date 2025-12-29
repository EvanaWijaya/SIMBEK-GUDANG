<?php

namespace App\Services\Stock;

use App\Models\Material;
use App\Models\FormulaDetail;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MaterialConsumptionService
{
    /**
     * Dipanggil dari ProductionService
     */
    public function consumeForProduction(
        int $productId,
        float $productionQty,
        int $productionId
    ): void {
        // Ambil formula detail (BOM)
        $formulaDetails = FormulaDetail::where('product_id', $productId)->get();

        foreach ($formulaDetails as $detail) {
            $materialId = $detail->material_id;
            $qtyNeeded  = $detail->qty * $productionQty;

            $this->consume(
                $materialId,
                $qtyNeeded,
                'production',
                $productionId,
                'Produksi barang'
            );
        }
    }

    /**
     * INTERNAL HELPER
     */
    protected function consume(
        int $materialId,
        float $qty,
        string $source,
        int $refId,
        ?string $notes = null
    ): void {
        $material = Material::lockForUpdate()->find($materialId);

        if (!$material) {
            throw new InvalidArgumentException('Material tidak ditemukan');
        }

        if ($material->stock < $qty) {
            throw new InvalidArgumentException('Stok material tidak mencukupi');
        }

        // Kurangi stok material
        $material->stock -= $qty;
        $material->save();

        // Catat pemakaian material
        DB::table('material_usages')->insert([
            'material_id' => $materialId,
            'qty' => $qty,
            'source' => $source,
            'ref_id' => $refId,
            'notes' => $notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
