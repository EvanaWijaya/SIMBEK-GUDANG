<?php

namespace App\Services;

use App\Models\Production;
use App\Models\Product;
use App\Models\Formula;
use App\Models\ProductStock;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * ========================================
 * PRODUCTION SERVICE - SIMBEK INVENTORY SYSTEM
 * ========================================
 * 
 * Service untuk manajemen produksi
 * Handle: Create production, deduct materials, generate product stock
 * 
 * Workflow:
 * 1. Validate formula & stock
 * 2. Deduct materials (via StockService)
 * 3. Create production record
 * 4. Generate product stock
 * 5. Record stock movements
 * 
 * Author: SIMBEK Team
 * Version: 1.0
 */
class ProductionService
{
    protected StockService $stockService;
    protected FormulaService $formulaService;

    public function __construct(
        StockService $stockService,
        FormulaService $formulaService
    ) {
        $this->stockService = $stockService;
        $this->formulaService = $formulaService;
    }

    /**
     * ========================================
     * PRODUCTION CREATION & PROCESSING
     * ========================================
     */

    /**
     * Process produksi lengkap
     * 
     * @param array $data [
     *   'product_id' => int,
     *   'formula_id' => int,
     *   'jumlah' => float (kg),
     *   'tgl_produksi' => date,
     *   'expired_date' => date (optional),
     *   'user_id' => int
     * ]
     * @return Production
     */
    public function processProduction(array $data): Production
    {
        // Validate input
        $this->validateProductionData($data);

        // Get formula & validate stock
        $formula = Formula::with('details.material')->findOrFail($data['formula_id']);
        
        if (!$formula->is_active) {
            throw new Exception("Formula '{$formula->nama_formula}' tidak aktif");
        }

        // Validate product matches formula
        if ($formula->product_id != $data['product_id']) {
            throw new Exception("Formula tidak sesuai dengan produk yang dipilih");
        }

        // Check material availability
        $validation = $this->formulaService->validateStockForProduction(
            $data['formula_id'], 
            $data['jumlah']
        );

        if (!$validation['is_sufficient']) {
            $insufficientMaterials = array_column($validation['insufficient_materials'], 'nama_material');
            throw new Exception(
                "Stok material tidak mencukupi untuk produksi. " .
                "Material kurang: " . implode(', ', $insufficientMaterials)
            );
        }

        return DB::transaction(function () use ($data, $formula, $validation) {
            // 1. Create production record (status: pending)
            $production = Production::create([
                'product_id' => $data['product_id'],
                'formula_id' => $data['formula_id'],
                'tgl_produksi' => $data['tgl_produksi'],
                'jumlah' => $data['jumlah'],
                'satuan' => 'kg',
                'expired_date' => $data['expired_date'] ?? $this->calculateExpiredDate($data['product_id']),
                'status' => 'pending',
                'user_id' => $data['user_id'],
            ]);

            // 2. Deduct materials from stock
            foreach ($formula->details as $detail) {
                $neededQty = $detail->qty * $data['jumlah'];
                
                $this->stockService->deductMaterialStock(
                    $detail->material_id,
                    $neededQty,
                    'production',
                    $production->id
                );
            }

            // 3. Update status to selesai (production completed)
            $production->update(['status' => 'selesai']);

            // 4. Create product stock
            $productStock = $this->stockService->addProductStock(
                $data['product_id'],
                $production->id,
                $data['jumlah']
            );

            // 5. Record product stock movement (masuk)
            $this->stockService->recordMovement([
                'tipe' => 'masuk',
                'sumber' => 'production',
                'qty' => $data['jumlah'],
                'product_stock_id' => $productStock->id,
                'ref_id' => $production->id,
                'notes' => "Produksi {$formula->product->nama_produk}",
            ]);

            return $production->fresh(['product', 'formula', 'user', 'productStocks']);
        });
    }

    /**
     * Validate production data
     */
    private function validateProductionData(array $data): void
    {
        if (!isset($data['product_id']) || !isset($data['formula_id']) || !isset($data['jumlah'])) {
            throw new Exception("Data produksi tidak lengkap");
        }

        if ($data['jumlah'] <= 0) {
            throw new Exception("Jumlah produksi harus lebih dari 0");
        }

        // Validate product exists
        Product::findOrFail($data['product_id']);
    }

    /**
     * Calculate expired date based on product type
     * Default: 6 bulan untuk pakan, 2 tahun untuk obat
     */
    private function calculateExpiredDate(int $productId): string
    {
        $product = Product::findOrFail($productId);
        
        $months = match(strtolower($product->kategori)) {
            'pakan' => 6,
            'obat' => 24,
            'suplemen' => 18,
            default => 12,
        };

        return now()->addMonths($months)->format('Y-m-d');
    }

    /**
     * ========================================
     * PRODUCTION STATUS MANAGEMENT
     * ========================================
     */

    /**
     * Update production status
     */
    public function updateStatus(int $productionId, string $status): Production
    {
        $production = Production::findOrFail($productionId);

        // Validate status transition
        $allowedTransitions = [
            'pending' => ['selesai', 'batal'],
            'selesai' => [], // Tidak bisa diubah kalau sudah selesai
            'batal' => [], // Tidak bisa diubah kalau sudah batal
        ];

        if (!in_array($status, $allowedTransitions[$production->status] ?? [])) {
            throw new Exception(
                "Status tidak bisa diubah dari '{$production->status}' ke '{$status}'"
            );
        }

        $production->update(['status' => $status]);

        return $production;
    }

    /**
     * Cancel production (hanya untuk status pending)
     */
    public function cancelProduction(int $productionId, int $userId): Production
    {
        $production = Production::with('formula.details')->findOrFail($productionId);

        if ($production->status !== 'pending') {
            throw new Exception("Hanya produksi dengan status 'pending' yang bisa dibatalkan");
        }

        return DB::transaction(function () use ($production) {
            // Return materials to stock
            foreach ($production->formula->details as $detail) {
                $returnedQty = $detail->qty * $production->jumlah;
                
                $this->stockService->addMaterialStock(
                    $detail->material_id,
                    $returnedQty,
                    'production_cancelled',
                    $production->id
                );
            }

            // Update status
            $production->update(['status' => 'batal']);

            return $production;
        });
    }

    /**
     * ========================================
     * PRODUCTION ANALYSIS
     * ========================================
     */

    /**
     * Get production summary untuk periode tertentu
     * 
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getProductionSummary(string $startDate, string $endDate): array
    {
        $productions = Production::whereBetween('tgl_produksi', [$startDate, $endDate])
            ->with(['product', 'formula'])
            ->get();

        $summary = [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'total_productions' => $productions->count(),
            'by_status' => [
                'selesai' => $productions->where('status', 'selesai')->count(),
                'pending' => $productions->where('status', 'pending')->count(),
                'batal' => $productions->where('status', 'batal')->count(),
            ],
            'total_qty_produced' => $productions->where('status', 'selesai')->sum('jumlah'),
            'by_product' => [],
            'total_cost' => 0,
        ];

        // Group by product
        $byProduct = $productions->where('status', 'selesai')->groupBy('product_id');
        
        foreach ($byProduct as $productId => $productProductions) {
            $product = $productProductions->first()->product;
            $totalQty = $productProductions->sum('jumlah');
            
            // Calculate total cost untuk produksi ini
            $totalProductCost = 0;
            foreach ($productProductions as $prod) {
                $costAnalysis = $this->formulaService->calculateProductionCost(
                    $prod->formula_id,
                    $prod->jumlah
                );
                $totalProductCost += $costAnalysis['cost_breakdown']['total_material_cost'];
            }

            $summary['by_product'][] = [
                'product_id' => $productId,
                'product_name' => $product->nama_produk,
                'total_qty' => $totalQty,
                'production_count' => $productProductions->count(),
                'total_cost' => $totalProductCost,
            ];

            $summary['total_cost'] += $totalProductCost;
        }

        return $summary;
    }

    /**
     * Get production efficiency
     * Membandingkan actual vs planned
     */
    public function getProductionEfficiency(int $productionId): array
    {
        $production = Production::with(['product', 'formula.details.material'])->findOrFail($productionId);

        if ($production->status !== 'selesai') {
            throw new Exception("Efficiency hanya bisa dihitung untuk produksi yang selesai");
        }

        // Calculate planned cost
        $costAnalysis = $this->formulaService->calculateProductionCost(
            $production->formula_id,
            $production->jumlah
        );

        // Calculate actual material usage (dari stock movements)
        $actualMaterialUsage = DB::table('stock_movements')
            ->where('sumber', 'production')
            ->where('ref_id', $production->id)
            ->where('tipe', 'keluar')
            ->whereNotNull('material_id')
            ->join('materials', 'materials.id', '=', 'stock_movements.material_id')
            ->select('materials.nama_material', 'stock_movements.qty', 'materials.harga')
            ->get();

        $actualCost = $actualMaterialUsage->sum(function($item) {
            return $item->qty * $item->harga;
        });

        $plannedCost = $costAnalysis['cost_breakdown']['total_material_cost'];
        $variance = $actualCost - $plannedCost;
        $efficiencyPercent = $plannedCost > 0 
            ? (($plannedCost - abs($variance)) / $plannedCost) * 100 
            : 100;

        return [
            'production_id' => $productionId,
            'product_name' => $production->product->nama_produk,
            'qty_produced' => $production->jumlah,
            'cost_analysis' => [
                'planned_cost' => $plannedCost,
                'actual_cost' => $actualCost,
                'variance' => $variance,
                'variance_percent' => $plannedCost > 0 ? ($variance / $plannedCost) * 100 : 0,
            ],
            'efficiency_percent' => max(0, min(100, $efficiencyPercent)),
            'status' => $variance <= 0 ? 'efficient' : 'over_budget',
            'material_usage' => $actualMaterialUsage->map(function($item) {
                return [
                    'material' => $item->nama_material,
                    'qty_used' => $item->qty,
                    'cost' => $item->qty * $item->harga,
                ];
            })->toArray(),
        ];
    }

    /**
     * ========================================
     * PRODUCTION PLANNING
     * ========================================
     */

    /**
     * Simulasi produksi (tanpa execute)
     * Untuk planning & decision making
     */
    public function simulateProduction(int $formulaId, float $qty): array
    {
        // Validate stock
        $validation = $this->formulaService->validateStockForProduction($formulaId, $qty);
        
        // Calculate cost & revenue
        $costAnalysis = $this->formulaService->calculateProductionCost($formulaId, $qty);
        
        // Get formula efficiency
        $efficiency = $this->formulaService->getFormulaEfficiency($formulaId);

        $formula = Formula::with('product')->findOrFail($formulaId);

        return [
            'formula' => [
                'id' => $formula->id,
                'name' => $formula->nama_formula,
                'product' => $formula->product->nama_produk,
            ],
            'planned_qty' => $qty,
            'can_produce' => $validation['is_sufficient'],
            'stock_validation' => $validation,
            'cost_analysis' => $costAnalysis,
            'efficiency_score' => $efficiency['scores']['overall_efficiency'],
            'recommendation' => $this->generateRecommendation($validation, $costAnalysis, $efficiency),
        ];
    }

    /**
     * Generate recommendation based on simulation
     */
    private function generateRecommendation(array $validation, array $costAnalysis, array $efficiency): string
    {
        if (!$validation['is_sufficient']) {
            return 'Tidak disarankan: Stok material tidak mencukupi. Lakukan restock terlebih dahulu.';
        }

        $margin = $costAnalysis['revenue_analysis']['margin_percent'];
        $efficiencyScore = $efficiency['scores']['overall_efficiency'];

        if ($margin < 20) {
            return 'Perhatian: Margin terlalu rendah (< 20%). Pertimbangkan untuk menaikkan harga jual atau cari supplier yang lebih murah.';
        }

        if ($efficiencyScore < 60) {
            return 'Perhatian: Efficiency score rendah. Pertimbangkan menggunakan formula alternatif yang lebih efisien.';
        }

        return 'Rekomendasi: Produksi dapat dilanjutkan. Semua parameter dalam kondisi baik.';
    }

    /**
     * Get optimal production quantity
     * Berdasarkan: available stock, demand, ROP
     */
    public function getOptimalProductionQty(int $formulaId): array
    {
        $formula = Formula::with('details.material')->findOrFail($formulaId);
        
        // Cari material yang paling limiting (bottleneck)
        $maxProducibleByMaterial = [];

        foreach ($formula->details as $detail) {
            $availableStock = $detail->material->stok;
            $requiredPerKg = $detail->qty;
            
            // Max qty yang bisa diproduksi berdasarkan material ini
            $maxQty = $requiredPerKg > 0 ? floor($availableStock / $requiredPerKg) : 0;
            
            $maxProducibleByMaterial[] = [
                'material' => $detail->material->nama_material,
                'max_qty' => $maxQty,
                'is_bottleneck' => false,
            ];
        }

        // Sort untuk cari bottleneck
        usort($maxProducibleByMaterial, fn($a, $b) => $a['max_qty'] <=> $b['max_qty']);
        
        // Material pertama (qty terkecil) adalah bottleneck
        if (!empty($maxProducibleByMaterial)) {
            $maxProducibleByMaterial[0]['is_bottleneck'] = true;
        }

        $optimalQty = $maxProducibleByMaterial[0]['max_qty'] ?? 0;

        return [
            'formula_id' => $formulaId,
            'formula_name' => $formula->nama_formula,
            'optimal_qty' => $optimalQty,
            'unit' => 'kg',
            'limiting_factors' => $maxProducibleByMaterial,
            'recommendation' => $optimalQty > 0 
                ? "Dapat memproduksi maksimal {$optimalQty} kg berdasarkan stok material yang tersedia."
                : "Tidak dapat memproduksi. Lakukan restock material terlebih dahulu.",
        ];
    }
}