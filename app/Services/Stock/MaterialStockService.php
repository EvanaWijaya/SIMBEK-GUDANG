<?php

namespace App\Services\Stock;

use App\Models\Material;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MaterialStockService extends BaseStockService
{
    /**
     * Tambah stok material (purchase / adjustment naik)
     */
    public function addStock(
        Material $material,
        float $qty,
        string $sumber,
        ?int $refId = null,
        ?string $notes = null
    ): void {
        $this->beginStockTransaction(function () use ($material, $qty, $sumber, $refId, $notes) {
            // Update stok material
            $this->increaseStock($material, $qty);

            // Catat stock movement
            $this->recordStockMovement([
                'tipe' => 'masuk',
                'sumber' => $sumber,
                'qty' => $qty,
                'material_id' => $material->id,
                'ref_id' => $refId,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Kurangi stok material (production / disposal / adjustment turun)
     */
    public function reduceStock(
        Material $material,
        float $qty,
        string $sumber,
        ?int $refId = null,
        ?string $notes = null
    ): void {
        $this->beginStockTransaction(function () use ($material, $qty, $sumber, $refId, $notes) {
            // Validasi stok cukup
            $this->assertStockSufficient($material, $qty);

            // Kurangi stok
            $this->decreaseStock($material, $qty);

            // Catat stock movement
            $this->recordStockMovement([
                'tipe' => 'keluar',
                'sumber' => $sumber,
                'qty' => $qty,
                'material_id' => $material->id,
                'ref_id' => $refId,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Cek apakah material perlu restock (ROP-based)
     */
    public function needsRestock(Material $material, int $usageDays = 30): bool
    {
        $averageDailyUsage = $this->calculateAverageDailyUsage(
            $material->id,
            $usageDays
        );

        $rop = $this->calculateROP(
            (float) $averageDailyUsage,
            (int) $material->lead_time_days,
            (float) ($material->safety_stock ?? 0)
        );


        return $material->stok <= $rop;
    }

    /**
     * Ambil data ROP material (untuk inventory / laporan)
     */
    public function getRopData(Material $material, int $usageDays = 30): array
    {
        $averageDailyUsage = $this->calculateAverageDailyUsage(
            $material->id,
            $usageDays
        );

        $rop = $this->calculateROP(
            (float) $averageDailyUsage,
            (int) $material->lead_time_days,
            (float) ($material->safety_stock ?? 0)
        );


        return [
            'stok_sekarang' => $material->stok,
            'average_daily_usage' => $averageDailyUsage,
            'lead_time_days' => $material->lead_time_days,
            'safety_stock' => $material->safety_stock,
            'rop' => $rop,
            'needs_restock' => $material->stok <= $rop,
        ];
    }
}
