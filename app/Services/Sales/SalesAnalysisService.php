<?php

namespace App\Services\Sales;

use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesAnalysisService
{
    protected int $defaultDays = 30;

    /**
     * Summary penjualan
     */
    public function summary(int $days = null): array
    {
        $days = $days ?? $this->defaultDays;

        return [
            'total_sales'   => $this->totalSales($days),
            'total_revenue' => $this->totalRevenue($days),
            'top_products'  => $this->topSellingProducts($days),
            'trend'         => $this->salesTrend($days),
        ];
    }

    protected function totalSales(int $days): int
    {
        return Sale::whereDate('created_at', '>=', Carbon::now()->subDays($days))
            ->count();
    }

    protected function totalRevenue(int $days): float
    {
        return Sale::whereDate('created_at', '>=', Carbon::now()->subDays($days))
            ->sum('total_harga');
    }

    protected function topSellingProducts(int $days): array
    {
        return DB::table('sales')
            ->select('product_id', DB::raw('SUM(qty) as total_qty'))
            ->whereDate('created_at', '>=', Carbon::now()->subDays($days))
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get()
            ->toArray();
    }

    protected function salesTrend(int $days): array
    {
        return Sale::select(
                DB::raw('DATE(created_at) as tanggal'),
                DB::raw('SUM(total_harga) as total')
            )
            ->whereDate('created_at', '>=', Carbon::now()->subDays($days))
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get()
            ->toArray();
    }
}
