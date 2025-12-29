<?php

namespace App\Services\Analytics;

use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StockAnalyticsService
{
    protected int $defaultDays = 30;

    /**
     * Fast vs Slow Moving Items
     */
    public function fastSlowMoving(int $days = null): array
    {
        $days = $days ?? $this->defaultDays;
        $startDate = Carbon::today()->subDays($days - 1);

        return [
            'materials' => $this->analyzeMaterials($startDate),
            'products'  => $this->analyzeProducts($startDate),
            'periode'   => $days . ' hari',
        ];
    }

    /**
     * Analisis material
     */
    protected function analyzeMaterials($startDate): array
    {
        $rows = StockMovement::select(
                'material_id',
                DB::raw('SUM(qty) as total_keluar')
            )
            ->where('tipe', 'keluar')
            ->whereNotNull('material_id')
            ->whereDate('created_at', '>=', $startDate)
            ->groupBy('material_id')
            ->orderByDesc('total_keluar')
            ->get();

        return $this->classify($rows, 'material_id');
    }

    /**
     * Analisis produk jadi
     */
    protected function analyzeProducts($startDate): array
    {
        $rows = StockMovement::select(
                'product_stock_id',
                DB::raw('SUM(qty) as total_keluar')
            )
            ->where('tipe', 'keluar')
            ->whereNotNull('product_stock_id')
            ->whereDate('created_at', '>=', $startDate)
            ->groupBy('product_stock_id')
            ->orderByDesc('total_keluar')
            ->get();

        return $this->classify($rows, 'product_stock_id');
    }

    /**
     * Klasifikasi Fast vs Slow Moving
     */
    protected function classify($rows, string $key): array
    {
        $count = $rows->count();

        if ($count === 0) {
            return [
                'fast_moving' => [],
                'slow_moving' => [],
            ];
        }

        $fastLimit = (int) ceil($count * 0.3);
        $slowLimit = (int) ceil($count * 0.3);

        return [
            'fast_moving' => $rows->take($fastLimit)->values()->map(fn ($row) => [
                $key        => $row->$key,
                'total_out' => (float) $row->total_keluar,
            ]),
            'slow_moving' => $rows->slice(-$slowLimit)->values()->map(fn ($row) => [
                $key        => $row->$key,
                'total_out' => (float) $row->total_keluar,
            ]),
        ];
    }

    /**
     * Stock value (opsional tapi sering dipakai)
     */
    public function stockValue(): array
    {
        $materialValue = DB::table('materials')
            ->select(DB::raw('SUM(stok * harga) as total'))
            ->value('total') ?? 0;

        $productValue = DB::table('product_stock')
            ->join('products', 'products.id', '=', 'product_stock.product_id')
            ->select(DB::raw('SUM(product_stock.qty * products.harga) as total'))
            ->value('total') ?? 0;

        return [
            'materials' => (float) $materialValue,
            'products'  => (float) $productValue,
            'total'     => (float) ($materialValue + $productValue),
        ];
    }
}
