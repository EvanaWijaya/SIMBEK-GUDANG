<?php

namespace App\Services\Stock;

use App\Models\Material;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Support\Facades\DB;

/**
 * ========================================
 * STOCK REPORT SERVICE
 * ========================================
 * 
 * Responsibility: Stock reports & summaries ONLY
 * - Material stock summary
 * - Product stock summary
 * - Stock value calculations
 * - Low stock alerts
 * 
 * @package App\Services\Stock
 * @version 2.0 (Refactored)
 */
class StockReportService
{
    /**
     * Get material stock summary
     * 
     * @return array
     */
    public function getMaterialSummary(): array
    {
        return [
            'total_materials' => Material::count(),
            'low_stock_count' => Material::lowStock()->count(),
            'total_stock_value' => Material::sum(DB::raw('stok * harga')),
            'materials' => Material::select('id', 'nama_material', 'stok', 'stok_min', 'satuan')
                ->get()
                ->map(function($material) {
                    return [
                        'id' => $material->id,
                        'nama' => $material->nama_material,
                        'stok' => $material->stok,
                        'stok_min' => $material->stok_min,
                        'status' => $material->stock_status,
                    ];
                })
                ->toArray(),
        ];
    }

    /**
     * Get product stock summary
     * 
     * @return array
     */
    public function getProductSummary(): array
    {
        $products = Product::with('productStocks')->get();
        $totalValue = 0;
        $lowStockCount = 0;
        $productDetails = [];

        foreach ($products as $product) {
            $totalStock = $product->productStocks->sum('qty');
            $value = $totalStock * $product->harga_jual;
            $totalValue += $value;
            
            // Consider low stock if < 10 kg
            if ($totalStock < 10 && $totalStock > 0) {
                $lowStockCount++;
            }

            $productDetails[] = [
                'id' => $product->id,
                'nama' => $product->nama_produk,
                'total_stock' => $totalStock,
                'stock_value' => $value,
                'status' => $this->getProductStockStatus($totalStock),
            ];
        }

        return [
            'total_products' => $products->count(),
            'products_with_stock' => $products->filter(fn($p) => $p->total_stock > 0)->count(),
            'low_stock_count' => $lowStockCount,
            'total_stock_value' => round($totalValue, 2),
            'products' => $productDetails,
        ];
    }

    /**
     * Get low stock materials
     * 
     * @return array
     */
    public function getLowStockMaterials(): array
    {
        return Material::lowStock()
            ->get()
            ->map(function($material) {
                return [
                    'id' => $material->id,
                    'nama' => $material->nama_material,
                    'current_stock' => $material->stok,
                    'min_stock' => $material->stok_min,
                    'shortage' => max(0, $material->stok_min - $material->stok),
                    'supplier' => $material->supplier,
                ];
            })
            ->toArray();
    }

    /**
     * Get stock value breakdown
     * 
     * @return array
     */
    public function getStockValueBreakdown(): array
    {
        $materialValue = Material::sum(DB::raw('stok * harga'));
        
        $productValue = 0;
        $products = Product::with('productStocks')->get();
        foreach ($products as $product) {
            $totalStock = $product->productStocks->sum('qty');
            $productValue += $totalStock * $product->harga_jual;
        }

        $totalValue = $materialValue + $productValue;

        return [
            'material_stock_value' => round($materialValue, 2),
            'product_stock_value' => round($productValue, 2),
            'total_stock_value' => round($totalValue, 2),
            'breakdown' => [
                'materials_percentage' => $totalValue > 0 ? round(($materialValue / $totalValue) * 100, 2) : 0,
                'products_percentage' => $totalValue > 0 ? round(($productValue / $totalValue) * 100, 2) : 0,
            ],
        ];
    }

    /**
     * Get near expiry product stocks
     * 
     * @param int $daysThreshold
     * @return array
     */
    public function getNearExpiryProducts(int $daysThreshold = 30): array
    {
        $targetDate = now()->addDays($daysThreshold);

        $nearExpiry = ProductStock::where('qty', '>', 0)
            ->with(['product', 'production'])
            ->whereHas('production', function($query) use ($targetDate) {
                $query->where('expired_date', '<=', $targetDate);
            })
            ->get();

        return $nearExpiry->map(function($stock) {
            $expiredDate = $stock->production->expired_date;
            $daysUntilExpired = now()->diffInDays($expiredDate, false);

            return [
                'product_stock_id' => $stock->id,
                'product_name' => $stock->product->nama_produk,
                'qty' => $stock->qty,
                'expired_date' => $expiredDate,
                'days_until_expired' => round($daysUntilExpired),
                'status' => $daysUntilExpired <= 0 ? 'expired' : 'near_expiry',
            ];
        })->toArray();
    }

    /**
     * Helper: Get product stock status
     */
    private function getProductStockStatus(float $stock): string
    {
        if ($stock <= 0) {
            return 'out_of_stock';
        } elseif ($stock < 10) {
            return 'low_stock';
        } elseif ($stock < 50) {
            return 'normal';
        } else {
            return 'berlimpah';
        }
    }
}