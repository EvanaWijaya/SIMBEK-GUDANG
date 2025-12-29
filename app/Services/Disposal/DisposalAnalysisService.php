<?php

namespace App\Services\Disposal;

use App\Models\StockDisposal;
use Illuminate\Support\Facades\DB;

class DisposalAnalysisService
{
    /**
     * ===============================
     * DISPOSAL SUMMARY
     * ===============================
     */
    public function getDisposalSummary(string $startDate, string $endDate): array
    {
        $disposals = StockDisposal::with([
                'productStock.product',
                'productStock.production'
            ])
            ->whereBetween('tgl_disposal', [$startDate, $endDate])
            ->get();

        // ===============================
        // SUMMARY UMUM
        // ===============================
        $totalQty = $disposals->sum('qty');

        $totalLoss = $disposals->sum(fn ($d) => 
            $this->calculateDisposalLoss($d)
        );

        // ===============================
        // GROUP BY ALASAN
        // ===============================
        $byReason = $disposals->groupBy('alasan')->map(function ($items, $alasan) {
            return [
                'alasan'        => $alasan,
                'alasan_label'  => $this->getReasonLabel($alasan),
                'count'         => $items->count(),
                'total_qty'     => $items->sum('qty'),
                'total_loss'    => $items->sum(fn ($d) => $this->calculateDisposalLoss($d)),
            ];
        })->values();

        // ===============================
        // GROUP BY PRODUCT
        // ===============================
        $byProduct = $disposals->groupBy(
            fn ($d) => optional($d->productStock)->product_id
        )->map(function ($items) {
            $first = $items->first();

            return [
                'product_id'    => $first->productStock->product_id,
                'product_name'  => $first->productStock->product->nama,
                'count'         => $items->count(),
                'total_qty'     => $items->sum('qty'),
                'total_loss'    => $items->sum(fn ($d) => $this->calculateDisposalLoss($d)),
            ];
        })->values();

        return [
            'period' => [
                'start' => $startDate,
                'end'   => $endDate,
            ],
            'summary' => [
                'total_disposals' => $disposals->count(),
                'total_qty'       => $totalQty,
                'total_loss'      => $totalLoss,
            ],
            'by_reason'  => $byReason,
            'by_product' => $byProduct,
        ];
    }

    /**
     * ===============================
     * DISPOSAL TREND
     * ===============================
     */
    public function getDisposalTrend(
        string $startDate,
        string $endDate,
        string $groupBy = 'monthly'
    ): array {
        $disposals = StockDisposal::whereBetween('tgl_disposal', [$startDate, $endDate])->get();

        $trend = [];

        foreach ($disposals as $disposal) {
            $date = $disposal->tgl_disposal;

            $key = match ($groupBy) {
                'daily'   => $date->format('Y-m-d'),
                'weekly'  => $date->format('Y-W'),
                default   => $date->format('Y-m'),
            };

            if (!isset($trend[$key])) {
                $trend[$key] = [
                    'period'     => $key,
                    'count'      => 0,
                    'total_qty'  => 0,
                    'total_loss' => 0,
                ];
            }

            $trend[$key]['count']++;
            $trend[$key]['total_qty'] += $disposal->qty;
            $trend[$key]['total_loss'] += $this->calculateDisposalLoss($disposal);
        }

        return [
            'period' => [
                'start'    => $startDate,
                'end'      => $endDate,
                'group_by' => $groupBy,
            ],
            'data' => array_values($trend),
        ];
    }

    /**
     * ===============================
     * DISPOSAL RATE
     * ===============================
     */
    public function getDisposalRate(string $startDate, string $endDate): array
    {
        $totalProduction = DB::table('productions')
            ->whereBetween('tgl_produksi', [$startDate, $endDate])
            ->where('status', 'selesai')
            ->sum('jumlah_produksi');

        $disposals = StockDisposal::with('productStock')
            ->whereBetween('tgl_disposal', [$startDate, $endDate])
            ->get();

        $totalDisposal = $disposals->sum('qty');

        $rate = $totalProduction > 0
            ? round(($totalDisposal / $totalProduction) * 100, 2)
            : 0;

        return [
            'period' => [
                'start' => $startDate,
                'end'   => $endDate,
            ],
            'overall' => [
                'total_production' => $totalProduction,
                'total_disposal'   => $totalDisposal,
                'rate_percent'     => $rate,
                'status'           => $this->getDisposalRateStatus($rate),
            ],
        ];
    }

    /**
     * ===============================
     * LOSS CALCULATION
     * ===============================
     */
    private function calculateDisposalLoss(StockDisposal $disposal): float
    {
        $production = optional($disposal->productStock)->production;

        if (!$production || !$production->formula_id) {
            return 0;
        }

        $costPerUnit = DB::table('formula_details')
            ->join('materials', 'materials.id', '=', 'formula_details.material_id')
            ->where('formula_id', $production->formula_id)
            ->sum(DB::raw('formula_details.qty * materials.harga'));

        return $disposal->qty * $costPerUnit;
    }

    /**
     * ===============================
     * HELPERS
     * ===============================
     */
    private function getDisposalRateStatus(float $rate): string
    {
        return match (true) {
            $rate <= 2  => 'excellent',
            $rate <= 5  => 'good',
            $rate <= 10 => 'warning',
            default     => 'critical',
        };
    }

    private function getReasonLabel(string $reason): string
    {
        return match ($reason) {
            'expired'  => 'Kadaluarsa',
            'rusak'    => 'Rusak',
            'hilang'   => 'Hilang',
            'lainnya'  => 'Lainnya',
            default    => ucfirst($reason),
        };
    }
}
