<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * ========================================
 * STOCK SERVICE - SIMBEK INVENTORY SYSTEM
 * ========================================
 * 
 * Service untuk manajemen stok material & produk
 * Include: ROP calculation, Safety Stock, FIFO, Adaptive recommendations
 * 
 * Author: SIMBEK Team
 * Version: 2.0 (with Adaptive)
 */
class StockService
{
    /**
     * ========================================
     * MATERIAL STOCK OPERATIONS
     * ========================================
     */

    /**
     * Cek apakah material tersedia dengan qty yang cukup
     */
    public function checkMaterialAvailability(int $materialId, float $qty): bool
    {
        $material = Material::find($materialId);
        
        if (!$material) {
            throw new Exception("Material dengan ID {$materialId} tidak ditemukan");
        }

        return $material->stok >= $qty;
    }

    /**
     * Kurangi stok material (untuk produksi)
     */
    public function deductMaterialStock(int $materialId, float $qty, string $reason, ?int $refId = null): void
    {
        $material = Material::findOrFail($materialId);

        if ($material->stok < $qty) {
            throw new Exception(
                "Stok material '{$material->nama_material}' tidak cukup. " .
                "Tersedia: {$material->stok} kg, Dibutuhkan: {$qty} kg"
            );
        }

        DB::transaction(function () use ($material, $qty, $reason, $refId) {
            $material->decrement('stok', $qty);

            $this->recordMovement([
                'tipe' => 'keluar',
                'sumber' => $reason,
                'qty' => $qty,
                'material_id' => $material->id,
                'ref_id' => $refId,
                'notes' => "Pengurangan stok material: {$material->nama_material}",
            ]);
        });
    }

    /**
     * Tambah stok material (untuk pembelian/adjustment)
     */
    public function addMaterialStock(int $materialId, float $qty, string $reason, ?int $refId = null): void
    {
        $material = Material::findOrFail($materialId);

        DB::transaction(function () use ($material, $qty, $reason, $refId) {
            $material->increment('stok', $qty);

            $this->recordMovement([
                'tipe' => 'masuk',
                'sumber' => $reason,
                'qty' => $qty,
                'material_id' => $material->id,
                'ref_id' => $refId,
                'notes' => "Penambahan stok material: {$material->nama_material}",
            ]);
        });
    }

    /**
     * ========================================
     * PRODUCT STOCK OPERATIONS
     * ========================================
     */

    /**
     * Cek apakah product stock tersedia
     */
    public function checkProductAvailability(int $productId, float $qty): bool
    {
        $totalStock = ProductStock::where('product_id', $productId)->sum('qty');
        return $totalStock >= $qty;
    }

    /**
     * Tambah stok produk (dari produksi)
     */
    public function addProductStock(int $productId, int $productionId, float $qty): ProductStock
    {
        return ProductStock::create([
            'product_id' => $productId,
            'production_id' => $productionId,
            'qty' => $qty,
        ]);
    }

    /**
     * Kurangi stok produk menggunakan FIFO (First In First Out)
     * 
     * FIFO memastikan produk lama keluar duluan (penting untuk expired date)
     */
    public function deductProductStock(int $productId, float $qty, string $reason, ?int $refId = null): void
    {
        $remainingQty = $qty;

        $productStocks = ProductStock::where('product_id', $productId)
            ->where('qty', '>', 0)
            ->orderBy('created_at', 'asc') // FIFO: oldest first
            ->get();

        $totalAvailable = $productStocks->sum('qty');
        if ($totalAvailable < $qty) {
            $product = Product::find($productId);
            throw new Exception(
                "Stok produk '{$product->nama_produk}' tidak cukup. " .
                "Tersedia: {$totalAvailable} kg, Dibutuhkan: {$qty} kg"
            );
        }

        DB::transaction(function () use ($productStocks, &$remainingQty, $reason, $refId) {
            foreach ($productStocks as $stock) {
                if ($remainingQty <= 0) break;

                $deductQty = min($stock->qty, $remainingQty);
                $stock->decrement('qty', $deductQty);
                
                $this->recordMovement([
                    'tipe' => 'keluar',
                    'sumber' => $reason,
                    'qty' => $deductQty,
                    'product_stock_id' => $stock->id,
                    'ref_id' => $refId,
                    'notes' => "Pengurangan stok produk (FIFO)",
                ]);

                $remainingQty -= $deductQty;
            }
        });
    }

    /**
     * Catat pergerakan stok
     */
    public function recordMovement(array $data): StockMovement
    {
        return StockMovement::create($data);
    }

    /**
     * ========================================
     * ROP & DAILY USAGE CALCULATION
     * ========================================
     * 
     * Formula:
     * - Daily Usage = Total Qty Keluar (30 hari) / 30
     * - ROP = (Lead Time × Daily Usage) + Safety Stock
     */

    /**
     * Hitung daily usage untuk material
     * Berdasarkan 30 hari terakhir
     * 
     * @return float kg/hari
     */
    public function calculateDailyUsage(int $materialId): float
    {
        $thirtyDaysAgo = now()->subDays(30);

        $totalUsage = StockMovement::where('material_id', $materialId)
            ->where('tipe', 'keluar')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->sum('qty');

        return $totalUsage / 30;
    }

    /**
     * Hitung ROP (Reorder Point)
     * 
     * Formula: ROP = (Lead Time × Daily Usage) + Safety Stock
     * 
     * @return float kg
     */
    public function calculateROP(int $materialId): float
    {
        $material = Material::findOrFail($materialId);
        $dailyUsage = $this->calculateDailyUsage($materialId);

        $rop = ($material->lead_time_days * $dailyUsage) + $material->safety_stock;

        return round($rop, 2);
    }

    /**
     * Cek apakah material perlu restock
     */
    public function needsRestock(int $materialId): bool
    {
        $material = Material::findOrFail($materialId);
        $rop = $this->calculateROP($materialId);

        return $material->stok <= $rop;
    }

    /**
     * Get semua material yang perlu restock
     */
    public function getMaterialsNeedRestock(): array
    {
        $materials = Material::all();
        $needRestock = [];

        foreach ($materials as $material) {
            $rop = $this->calculateROP($material->id);
            
            if ($material->stok <= $rop) {
                $dailyUsage = $this->calculateDailyUsage($material->id);
                
                $needRestock[] = [
                    'material_id' => $material->id,
                    'nama_material' => $material->nama_material,
                    'current_stock' => $material->stok,
                    'safety_stock' => $material->safety_stock,
                    'rop' => $rop,
                    'daily_usage' => round($dailyUsage, 2),
                    'lead_time_days' => $material->lead_time_days,
                    'status' => $material->stok <= $material->safety_stock ? 'critical' : 'warning',
                    'days_until_stockout' => $dailyUsage > 0 
                        ? round($material->stok / $dailyUsage, 0) 
                        : null,
                ];
            }
        }

        return $needRestock;
    }

    /**
     * ========================================
     * ADAPTIVE SAFETY STOCK (FASE 2+)
     * ========================================
     * 
     * Formula: Safety Stock = Z-Score × σ × √Lead Time
     * 
     * Z-Score (Service Level):
     * - 90% = 1.28
     * - 95% = 1.65
     * - 99% = 2.33
     * 
     * σ = Standard Deviation dari daily usage
     */

    /**
     * Cek apakah material punya cukup data untuk auto-calculate
     */
    public function hasEnoughDataForAuto(int $materialId): bool
    {
        $thirtyDaysAgo = now()->subDays(30);
        
        $transactionCount = StockMovement::where('material_id', $materialId)
            ->where('tipe', 'keluar')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        return $transactionCount >= 20;
    }

    /**
     * Hitung safety stock otomatis
     * 
     * @param float $serviceLevel (0.95 = 95%)
     * @return float kg
     */
    public function calculateAutoSafetyStock(int $materialId, float $serviceLevel = 0.95): float
    {
        $material = Material::findOrFail($materialId);
        $dailyUsages = $this->getDailyUsageHistory($materialId, 60);

        if (count($dailyUsages) < 30) {
            return $material->stok_min * 0.5;
        }

        $stdDev = $this->calculateStandardDeviation($dailyUsages);
        $zScore = $this->getZScore($serviceLevel);

        $safetyStock = $zScore * $stdDev * sqrt($material->lead_time_days);

        return round($safetyStock, 2);
    }

    /**
     * Get daily usage history
     */
    private function getDailyUsageHistory(int $materialId, int $days): array
    {
        $startDate = now()->subDays($days);
        $dailyUsages = [];

        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            
            $usage = StockMovement::where('material_id', $materialId)
                ->where('tipe', 'keluar')
                ->whereDate('created_at', $date)
                ->sum('qty');

            if ($usage > 0) {
                $dailyUsages[] = $usage;
            }
        }

        return $dailyUsages;
    }

    /**
     * Hitung standard deviation
     * Formula: σ = √(Σ(xi - x̄)² / (n-1))
     */
    private function calculateStandardDeviation(array $data): float
    {
        if (count($data) < 2) return 0;

        $mean = array_sum($data) / count($data);
        $variance = 0;

        foreach ($data as $value) {
            $variance += pow($value - $mean, 2);
        }

        $variance = $variance / (count($data) - 1);
        return sqrt($variance);
    }

    /**
     * Get Z-Score based on service level
     */
    private function getZScore(float $serviceLevel): float
    {
        $zScores = [
            0.90 => 1.28,
            0.95 => 1.65,
            0.97 => 1.88,
            0.99 => 2.33,
            0.995 => 2.58,
        ];

        return $zScores[$serviceLevel] ?? 1.65;
    }

    /**
     * Get rekomendasi safety stock
     */
    public function getSafetyStockRecommendation(int $materialId): array
    {
        $material = Material::findOrFail($materialId);

        if (!$this->hasEnoughDataForAuto($materialId)) {
            return [
                'material_id' => $materialId,
                'nama_material' => $material->nama_material,
                'current_safety_stock' => $material->safety_stock,
                'recommended_safety_stock' => null,
                'status' => 'insufficient_data',
                'message' => 'Data historis belum cukup (minimal 30 hari dengan 20+ transaksi)',
            ];
        }

        $recommendedSafetyStock = $this->calculateAutoSafetyStock($materialId, 0.95);
        $variance = $recommendedSafetyStock - $material->safety_stock;
        $variancePercent = $material->safety_stock > 0 
            ? ($variance / $material->safety_stock) * 100 
            : 0;

        $action = $this->determineAction($variance, $variancePercent);

        return [
            'material_id' => $materialId,
            'nama_material' => $material->nama_material,
            'current_safety_stock' => $material->safety_stock,
            'recommended_safety_stock' => $recommendedSafetyStock,
            'variance' => round($variance, 2),
            'variance_percent' => round($variancePercent, 2),
            'status' => 'ready',
            'action' => $action,
            'daily_usage_avg' => $this->calculateDailyUsage($materialId),
        ];
    }

    private function determineAction(float $variance, float $variancePercent): string
    {
        if (abs($variancePercent) < 10) {
            return 'ok';
        } elseif ($variance > 0 && $variancePercent > 20) {
            return 'increase_critical';
        } elseif ($variance > 0) {
            return 'increase_recommended';
        } elseif ($variance < 0 && $variancePercent < -20) {
            return 'decrease_recommended';
        }
        return 'review';
    }

    /**
     * Get rekomendasi untuk semua material
     */
    public function getAllRecommendations(): array
    {
        $materials = Material::all();
        $recommendations = [];

        foreach ($materials as $material) {
            $rec = $this->getSafetyStockRecommendation($material->id);
            
            if ($rec['status'] === 'ready' && $rec['action'] !== 'ok') {
                $recommendations[] = $rec;
            }
        }

        usort($recommendations, function($a, $b) {
            $priority = [
                'increase_critical' => 1,
                'increase_recommended' => 2,
                'decrease_recommended' => 3,
                'review' => 4,
            ];
            return $priority[$a['action']] - $priority[$b['action']];
        });

        return $recommendations;
    }

    /**
     * ========================================
     * DELAY BUFFER CALCULATION
     * ========================================
     * 
     * Formula: Delay Buffer = Daily Usage × Avg Delay Days
     */

    public function calculateDelayBuffer(int $materialId, float $avgDelayDays = 2): float
    {
        $dailyUsage = $this->calculateDailyUsage($materialId);
        return round($dailyUsage * $avgDelayDays, 2);
    }

    /**
     * Get complete recommendation (Safety Stock + Delay Buffer)
     */
    public function getCompleteRecommendation(int $materialId, float $avgDelayDays = 2): array
    {
        $material = Material::findOrFail($materialId);
        $recommendation = $this->getSafetyStockRecommendation($materialId);
        $delayBuffer = $this->calculateDelayBuffer($materialId, $avgDelayDays);
        $dailyUsage = $this->calculateDailyUsage($materialId);

        $totalRecommended = ($recommendation['recommended_safety_stock'] ?? $material->safety_stock) + $delayBuffer;

        return [
            'material_id' => $materialId,
            'nama_material' => $material->nama_material,
            'current_setup' => [
                'safety_stock' => $material->safety_stock,
                'lead_time_days' => $material->lead_time_days,
                'current_rop' => $this->calculateROP($materialId),
            ],
            'recommendation' => [
                'base_safety_stock' => $recommendation['recommended_safety_stock'] ?? $material->safety_stock,
                'delay_buffer' => $delayBuffer,
                'total_safety_stock' => $totalRecommended,
                'new_rop' => ($material->lead_time_days * $dailyUsage) + $totalRecommended,
            ],
            'analysis' => [
                'daily_usage' => $dailyUsage,
                'avg_delay_days' => $avgDelayDays,
                'data_sufficient' => $this->hasEnoughDataForAuto($materialId),
            ],
        ];
    }

    /**
     * ========================================
     * STOCK VALIDATION
     * ========================================
     */

    public function validateMaterialsForProduction(array $materials): array
    {
        $details = [];
        $isSufficient = true;

        foreach ($materials as $item) {
            $material = Material::find($item['material_id']);
            
            if (!$material) {
                $isSufficient = false;
                $details[] = [
                    'material_id' => $item['material_id'],
                    'error' => 'Material tidak ditemukan',
                    'is_sufficient' => false,
                ];
                continue;
            }

            $sufficient = $material->stok >= $item['qty'];
            
            if (!$sufficient) {
                $isSufficient = false;
            }

            $details[] = [
                'material_id' => $material->id,
                'nama_material' => $material->nama_material,
                'required_qty' => $item['qty'],
                'available_stock' => $material->stok,
                'is_sufficient' => $sufficient,
                'shortage' => $sufficient ? 0 : ($item['qty'] - $material->stok),
            ];
        }

        return [
            'is_sufficient' => $isSufficient,
            'details' => $details,
        ];
    }

    /**
     * ========================================
     * STOCK REPORTS
     * ========================================
     */

    public function getMaterialStockSummary(): array
    {
        return [
            'total_materials' => Material::count(),
            'low_stock_count' => Material::lowStock()->count(),
            'need_reorder_count' => count($this->getMaterialsNeedRestock()),
            'total_stock_value' => Material::sum(DB::raw('stok * harga')),
        ];
    }

    public function getProductStockSummary(): array
    {
        $products = Product::with('productStocks')->get();
        $totalValue = 0;
        $lowStockCount = 0;

        foreach ($products as $product) {
            $totalStock = $product->productStocks->sum('qty');
            $totalValue += $totalStock * $product->harga_jual;
            
            if ($totalStock < 10 && $totalStock > 0) {
                $lowStockCount++;
            }
        }

        return [
            'total_products' => $products->count(),
            'products_with_stock' => $products->filter(fn($p) => $p->total_stock > 0)->count(),
            'low_stock_count' => $lowStockCount,
            'total_stock_value' => round($totalValue, 2),
        ];
    }
}