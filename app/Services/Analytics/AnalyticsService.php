<?php

namespace App\Services\Analytics;

use App\Services\Sales\SalesAnalysisService;

class AnalyticsService
{
    public function __construct(
        protected MovementAnalyticsService $movement,
        protected StockAnalyticsService $stock,
        protected PlanningAnalyticsService $planning,
        protected ProductionAnalyticsService $production,
        protected SalesAnalysisService $sales,
    ) {}

    /**
     * Dashboard Kelola Gudang
     */
    public function dashboard(): array
    {
        return [
            'movement'   => $this->movement->todaySummary(),
            'stock'      => $this->stock->fastSlowMoving(),
            'planning'   => $this->planning->ropAlerts(),
            'production' => $this->production->efficiency(),
            'sales'      => $this->sales->summary(),
        ];
    }
}
