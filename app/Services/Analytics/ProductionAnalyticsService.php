<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductionAnalyticsService
{
    protected int $days = 30;

    /**
     * Production efficiency summary
     */
    public function efficiency(): array
    {
        return [
            'periode'        => $this->days . ' hari',
            'total_output'   => $this->totalOutput(),
            'material_usage' => $this->materialEfficiency(),
        ];
    }

    /**
     * Total output produksi
     */
    protected function totalOutput(): float
    {
        return (float) DB::table('production')
            ->whereDate('created_at', '>=', Carbon::today()->subDays($this->days - 1))
            ->sum('qty');
    }

    /**
     * Efisiensi pemakaian material
     */
    protected function materialEfficiency(): array
    {
        $rows = DB::table('formula_details')
            ->join('formula', 'formula.id', '=', 'formula_details.formula_id')
            ->join('production', 'production.product_id', '=', 'formula.product_id')
            ->select(
                'formula_details.material_id',
                DB::raw('SUM(formula_details.qty * production.qty) as standard_qty')
            )
            ->whereDate('production.created_at', '>=', Carbon::today()->subDays($this->days - 1))
            ->groupBy('formula_details.material_id')
            ->get();

        $efficiency = [];

        foreach ($rows as $row) {
            $actualUsage = DB::table('stock_movements')
                ->where('tipe', 'keluar')
                ->where('material_id', $row->material_id)
                ->where('sumber', 'production')
                ->whereDate('created_at', '>=', Carbon::today()->subDays($this->days - 1))
                ->sum('qty');

            $ratio = $row->standard_qty > 0
                ? $actualUsage / $row->standard_qty
                : 0;

            $efficiency[] = [
                'material_id'    => $row->material_id,
                'standard_qty'   => (float) $row->standard_qty,
                'actual_qty'     => (float) $actualUsage,
                'efficiency'     => round($ratio, 2),
            ];
        }

        return $efficiency;
    }
}
