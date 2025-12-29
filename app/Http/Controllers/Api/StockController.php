<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/* === SERVICES === */
use App\Services\Stock\MaterialPlanningService;
use App\Services\Stock\ProductStockService;
use App\Services\Stock\StockMovementService;
use App\Services\Stock\StockReportService;
use App\Services\Inventory\ROPService;
use App\Models\Material;

class StockController extends Controller
{
    protected MaterialPlanningService $materialPlanning;
    protected ProductStockService $productStock;
    protected StockMovementService $stockMovement;
    protected StockReportService $stockReport;
    protected ROPService $ropService;

    public function __construct(
        MaterialPlanningService $materialPlanning,
        ProductStockService $productStock,
        StockMovementService $stockMovement,
        StockReportService $stockReport,
        ROPService $ropService
    ) {
        $this->materialPlanning = $materialPlanning;
        $this->productStock = $productStock;
        $this->stockMovement = $stockMovement;
        $this->stockReport = $stockReport;
        $this->ropService = $ropService;
    }

    /**
     * =========================
     * MATERIAL STOCK (SUMMARY)
     * =========================
     */
    public function materials()
    {
        return response()->json([
            'success' => true,
            'data' => $this->stockReport->getMaterialSummary(),
        ]);
    }

    /**
     * =========================
     * PRODUCT STOCK
     * =========================
     */
    public function products()
    {
        return response()->json([
            'success' => true,
            'data' => $this->stockReport->getProductSummary(),
        ]);
    }

    /**
     * =========================
     * MATERIAL ROP & PLANNING
     * =========================
     */

    public function materialRop(int $materialId)
    {
        $material = Material::findOrFail($materialId);

        return response()->json([
            'success' => true,
            'data' => [
                'average_daily_usage' =>
                    $this->materialPlanning->getAverageDailyUsage($materialId),
                'rop' =>
                    $this->materialPlanning->calculateROP($material),
                'needs_restock' =>
                    $this->materialPlanning->needsRestock($material),
            ],
        ]);
    }

    /**
     * =========================
     * STOCK MOVEMENT
     * =========================
     */
    public function movements(Request $request)
    {
        if ($request->filled('material_id')) {
            $data = $this->stockMovement->getByMaterial($request->material_id);
        } elseif ($request->filled('product_id')) {
            $data = $this->stockMovement->getByProduct($request->product_id);
        } else {
            $data = $this->stockMovement->getToday();
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * =========================
     * STOCK REPORT
     * =========================
     */
    public function report()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'low_stock_materials' => $this->stockReport->getLowStockMaterials(),
                'stock_value' => $this->stockReport->getStockValueBreakdown(),
            ],
        ]);
    }
}
