<?php

namespace App\Services\Analytics;

use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MovementAnalyticsService
{
    /**
     * Summary barang masuk & keluar hari ini
     */
    public function todaySummary(): array
    {
        $today = Carbon::today();

        $data = StockMovement::select(
                'tipe',
                DB::raw('SUM(qty) as total_qty')
            )
            ->whereDate('created_at', $today)
            ->groupBy('tipe')
            ->pluck('total_qty', 'tipe');

        return [
            'tanggal' => $today->toDateString(),
            'masuk'   => (float) ($data['masuk'] ?? 0),
            'keluar'  => (float) ($data['keluar'] ?? 0),
        ];
    }

    /**
     * Tren barang keluar N hari terakhir
     */
    public function outTrend(int $days = 30): array
    {
        $startDate = Carbon::today()->subDays($days - 1);

        $rows = StockMovement::select(
                DB::raw('DATE(created_at) as tanggal'),
                DB::raw('SUM(qty) as total_keluar')
            )
            ->where('tipe', 'keluar')
            ->whereDate('created_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('tanggal')
            ->get();

        // Format agar frontend enak
        return $rows->map(fn ($row) => [
            'tanggal' => $row->tanggal,
            'qty'     => (float) $row->total_keluar,
        ])->toArray();
    }

    /**
     * Breakdown masuk vs keluar (global)
     */
    public function movementSummary(): array
    {
        $data = StockMovement::select(
                'tipe',
                DB::raw('SUM(qty) as total_qty')
            )
            ->groupBy('tipe')
            ->pluck('total_qty', 'tipe');

        return [
            'masuk'  => (float) ($data['masuk'] ?? 0),
            'keluar' => (float) ($data['keluar'] ?? 0),
        ];
    }

    /**
     * Summary per item (material & produk jadi)
     */
    public function byItem(): array
    {
        $materials = StockMovement::select(
                'material_id',
                DB::raw('SUM(qty) as total_qty'),
                'tipe'
            )
            ->whereNotNull('material_id')
            ->groupBy('material_id', 'tipe')
            ->get();

        $products = StockMovement::select(
                'product_stock_id',
                DB::raw('SUM(qty) as total_qty'),
                'tipe'
            )
            ->whereNotNull('product_stock_id')
            ->groupBy('product_stock_id', 'tipe')
            ->get();

        return [
            'materials' => $materials,
            'products'  => $products,
        ];
    }
}
