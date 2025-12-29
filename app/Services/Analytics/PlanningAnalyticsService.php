<?php

namespace App\Services\Analytics;

use App\Models\Material;
use App\Models\ProductPlanning;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlanningAnalyticsService
{
    protected int $defaultDays = 30;

    /**
     * Semua ROP alerts (material + product)
     */
    public function ropAlerts(): array
    {
        return [
            'materials' => $this->materialRopAlerts(),
            'products'  => $this->productRopAlerts(),
        ];
    }

    /**
     * ROP MATERIAL
     */
    protected function materialRopAlerts(): array
    {
        $materials = Material::all();
        $alerts = [];

        foreach ($materials as $material) {
            $avgDailyUsage = $this->avgDailyMaterialUsage($material->id);
            $rop = ($avgDailyUsage * $material->lead_time_days) + $material->safety_stock;

            if ($material->stok <= $rop) {
                $alerts[] = [
                    'material_id' => $material->id,
                    'nama'        => $material->nama_material,
                    'stok'        => (float) $material->stok,
                    'rop'         => round($rop, 2),
                    'status'      => 'RESTOCK',
                ];
            }
        }

        return $alerts;
    }

    /**
     * ROP PRODUCT (berdasarkan planning)
     */
    protected function productRopAlerts(): array
    {
        $plannings = ProductPlanning::with('product')->get();
        $alerts = [];

        foreach ($plannings as $plan) {
            $avgDailySales = $this->avgDailyProductSales($plan->product_id);
            $rop = ($avgDailySales * $plan->lead_time_days) + $plan->safety_stock;

            $currentStock = DB::table('product_stock')
                ->where('product_id', $plan->product_id)
                ->sum('qty');

            if ($currentStock <= $rop) {
                $alerts[] = [
                    'product_id' => $plan->product_id,
                    'nama'       => $plan->product->nama_product ?? '-',
                    'stok'       => (float) $currentStock,
                    'rop'        => round($rop, 2),
                    'status'     => 'PRODUCE',
                ];
            }
        }

        return $alerts;
    }

    /**
     * Rata-rata pemakaian material / hari
     */
    protected function avgDailyMaterialUsage(int $materialId): float
    {
        $total = StockMovement::where('material_id', $materialId)
            ->where('tipe', 'keluar')
            ->whereDate('created_at', '>=', Carbon::now()->subDays($this->defaultDays))
            ->sum('qty');

        return $total / $this->defaultDays;
    }

    /**
     * Rata-rata penjualan produk / hari
     */
    protected function avgDailyProductSales(int $productId): float
    {
        $total = StockMovement::where('tipe', 'keluar')
            ->where('sumber', 'sale')
            ->whereIn('product_stock_id', function ($q) use ($productId) {
                $q->select('id')
                  ->from('product_stock')
                  ->where('product_id', $productId);
            })
            ->whereDate('created_at', '>=', Carbon::now()->subDays($this->defaultDays))
            ->sum('qty');

        return $total / $this->defaultDays;
    }
}
