<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Analytics\MovementAnalyticsService;
use App\Services\Analytics\StockAnalyticsService;
use App\Services\Analytics\PlanningAnalyticsService;
use App\Services\Analytics\ProductionAnalyticsService;
use App\Services\Disposal\DisposalAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    protected MovementAnalyticsService $movementAnalytics;
    protected StockAnalyticsService $stockAnalytics;
    protected PlanningAnalyticsService $planningAnalytics;
    protected ProductionAnalyticsService $productionAnalytics;
    protected DisposalAnalysisService $disposalAnalytics;

    public function __construct(
        MovementAnalyticsService $movementAnalytics,
        StockAnalyticsService $stockAnalytics,
        PlanningAnalyticsService $planningAnalytics,
        ProductionAnalyticsService $productionAnalytics,
        DisposalAnalysisService $disposalAnalytics
    ) {
        $this->movementAnalytics = $movementAnalytics;
        $this->stockAnalytics = $stockAnalytics;
        $this->planningAnalytics = $planningAnalytics;
        $this->productionAnalytics = $productionAnalytics;
        $this->disposalAnalytics = $disposalAnalytics;
    }

    /**
     * Dashboard Kelola Gudang (Analytics)
     */
    public function dashboard(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);

        $endDate = now()->toDateString();
        $startDate = now()->subDays($days)->toDateString();

        return response()->json([
            'success' => true,
            'message' => 'Analytics dashboard retrieved successfully',
            'data' => [

                /**
                 * =====================
                 * STOCK & MOVEMENT
                 * =====================
                 */
                'summary_today' => $this->movementAnalytics->todaySummary(),
                'fast_slow_moving' => $this->stockAnalytics->fastSlowMoving($days),
                'stock_value' => $this->stockAnalytics->stockValue(),

                /**
                 * =====================
                 * PLANNING & PRODUCTION
                 * =====================
                 */
                'rop_alerts' => $this->planningAnalytics->ropAlerts(),
                'production_efficiency' => $this->productionAnalytics->efficiency(),

                /**
                 * =====================
                 * DISPOSAL (LOSS)
                 * =====================
                 */
                'disposal' => [
                    'summary' => $this->disposalAnalytics
                        ->getDisposalSummary($startDate, $endDate),

                    'trend' => $this->disposalAnalytics
                        ->getDisposalTrend($startDate, $endDate),

                    'rate' => $this->disposalAnalytics
                        ->getDisposalRate($startDate, $endDate),
                ],
            ],
        ]);
    }
}
