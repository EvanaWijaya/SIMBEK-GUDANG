<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * ========================================
 * SALES SERVICE - SIMBEK INVENTORY SYSTEM
 * ========================================
 * 
 * Service untuk manajemen penjualan produk
 * Handle: Create sale, deduct stock (FIFO), calculate profit
 * 
 * Workflow:
 * 1. Validate product & stock availability
 * 2. Calculate total price
 * 3. Deduct product stock (FIFO via StockService)
 * 4. Create sale record
 * 5. Record stock movement
 * 
 * Author: SIMBEK Team
 * Version: 1.0
 */
class SalesService
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * ========================================
     * SALES TRANSACTION
     * ========================================
     */

    /**
     * Create sale transaction
     * 
     * CONTEXT: Sale ini untuk transaksi penjualan produk (pakan/obat)
     * ke user yang login di sistem. User bisa beli pakan/obat seperti
     * mereka beli kambing di sistem lama.
     * 
     * @param array $data [
     *   'user_id' => int (pembeli - user yang login),
     *   'product_id' => int (pakan/obat yang dibeli),
     *   'qty' => float (kg),
     *   'metode_bayar' => string (cash/transfer/credit),
     *   'tgl_transaksi' => date
     * ]
     * @return Sale
     */
    public function createSale(array $data): Sale
    {
        // Validate input
        $this->validateSaleData($data);

        // Get product
        $product = Product::findOrFail($data['product_id']);

        // Check stock availability
        $availableStock = ProductStock::where('product_id', $data['product_id'])
            ->sum('qty');

        if ($availableStock < $data['qty']) {
            throw new Exception(
                "Stok produk '{$product->nama_produk}' tidak mencukupi. " .
                "Tersedia: {$availableStock} kg, Diminta: {$data['qty']} kg"
            );
        }

        // Calculate total price
        $totalHarga = $data['qty'] * $product->harga_jual;

        return DB::transaction(function () use ($data, $product, $totalHarga) {
            // 1. Create sale record
            // Note: user_id di sini adalah PEMBELI (user yang beli produk)
            // bukan admin yang input transaksi
            $sale = Sale::create([
                'user_id' => $data['user_id'],
                'product_id' => $data['product_id'],
                'qty' => $data['qty'],
                'total_harga' => $totalHarga,
                'metode_bayar' => $data['metode_bayar'],
                'status' => 'selesai',
                'tgl_transaksi' => $data['tgl_transaksi'] ?? now()->format('Y-m-d'),
            ]);

            // 2. Deduct product stock (FIFO)
            $this->stockService->deductProductStock(
                $data['product_id'],
                $data['qty'],
                'sale',
                $sale->id
            );

            return $sale->fresh(['user', 'product']);
        });
    }

    /**
     * Validate sale data
     */
    private function validateSaleData(array $data): void
    {
        $required = ['user_id', 'product_id', 'qty', 'metode_bayar'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Field '{$field}' wajib diisi");
            }
        }

        if ($data['qty'] <= 0) {
            throw new Exception("Jumlah penjualan harus lebih dari 0");
        }

        $allowedPaymentMethods = ['cash', 'transfer', 'credit'];
        if (!in_array(strtolower($data['metode_bayar']), $allowedPaymentMethods)) {
            throw new Exception(
                "Metode bayar tidak valid. Pilihan: " . implode(', ', $allowedPaymentMethods)
            );
        }
    }

    /**
     * ========================================
     * SALES STATUS MANAGEMENT
     * ========================================
     */

    /**
     * Update sale status
     */
    public function updateStatus(int $saleId, string $status): Sale
    {
        $sale = Sale::findOrFail($saleId);

        $allowedStatuses = ['pending', 'selesai', 'batal'];
        if (!in_array($status, $allowedStatuses)) {
            throw new Exception("Status tidak valid: {$status}");
        }

        // Validate status transition
        $allowedTransitions = [
            'pending' => ['selesai', 'batal'],
            'selesai' => ['batal'], // Bisa dibatalkan untuk retur
            'batal' => [], // Tidak bisa diubah lagi
        ];

        if (!in_array($status, $allowedTransitions[$sale->status] ?? [])) {
            throw new Exception(
                "Status tidak bisa diubah dari '{$sale->status}' ke '{$status}'"
            );
        }

        $sale->update(['status' => $status]);

        return $sale;
    }

    /**
     * Cancel sale & return stock
     * Untuk kasus retur atau pembatalan
     */
    public function cancelSale(int $saleId, string $reason = 'retur'): Sale
    {
        $sale = Sale::with('product')->findOrFail($saleId);

        if ($sale->status === 'batal') {
            throw new Exception("Penjualan sudah dibatalkan sebelumnya");
        }

        return DB::transaction(function () use ($sale, $reason) {
            // Return product to stock
            // Note: Ini akan create ProductStock baru, bukan return ke batch lama
            $production = DB::table('production')
                ->where('product_id', $sale->product_id)
                ->where('status', 'selesai')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($production) {
                $productStock = $this->stockService->addProductStock(
                    $sale->product_id,
                    $production->id,
                    $sale->qty
                );

                // Record stock movement
                $this->stockService->recordMovement([
                    'tipe' => 'masuk',
                    'sumber' => 'sale_cancelled',
                    'qty' => $sale->qty,
                    'product_stock_id' => $productStock->id,
                    'ref_id' => $sale->id,
                    'notes' => "Pembatalan penjualan. Alasan: {$reason}",
                ]);
            }

            // Update status
            $sale->update(['status' => 'batal']);

            return $sale;
        });
    }

    /**
     * ========================================
     * SALES ANALYSIS & REPORTS
     * ========================================
     */

    /**
     * Get sales summary untuk periode tertentu
     */
    public function getSalesSummary(string $startDate, string $endDate): array
    {
        $sales = Sale::whereBetween('tgl_transaksi', [$startDate, $endDate])
            ->with('product')
            ->get();

        // Group by product
        $byProduct = $sales->where('status', 'selesai')->groupBy('product_id');
        $productSummaries = [];

        foreach ($byProduct as $productId => $productSales) {
            $product = $productSales->first()->product;
            $totalQty = $productSales->sum('qty');
            $totalRevenue = $productSales->sum('total_harga');

            // Calculate cost (simplified - dari harga jual dan asumsi margin)
            // Untuk lebih akurat, perlu data production cost actual
            $avgSellingPrice = $totalRevenue / $totalQty;

            $productSummaries[] = [
                'product_id' => $productId,
                'product_name' => $product->nama_produk,
                'total_qty_sold' => $totalQty,
                'total_revenue' => $totalRevenue,
                'avg_selling_price' => round($avgSellingPrice, 2),
                'transaction_count' => $productSales->count(),
            ];
        }

        // Sort by revenue
        usort($productSummaries, fn($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'summary' => [
                'total_sales' => $sales->count(),
                'completed_sales' => $sales->where('status', 'selesai')->count(),
                'cancelled_sales' => $sales->where('status', 'batal')->count(),
                'total_revenue' => $sales->where('status', 'selesai')->sum('total_harga'),
                'total_qty_sold' => $sales->where('status', 'selesai')->sum('qty'),
            ],
            'by_product' => $productSummaries,
            'by_payment_method' => $this->getSalesByPaymentMethod($sales->where('status', 'selesai')),
        ];
    }

    /**
     * Get sales grouped by payment method
     */
    private function getSalesByPaymentMethod($sales): array
    {
        $byMethod = $sales->groupBy('metode_bayar');
        $methodSummaries = [];

        foreach ($byMethod as $method => $methodSales) {
            $methodSummaries[] = [
                'method' => $method,
                'count' => $methodSales->count(),
                'total_revenue' => $methodSales->sum('total_harga'),
                'percentage' => $sales->count() > 0 
                    ? round(($methodSales->count() / $sales->count()) * 100, 2) 
                    : 0,
            ];
        }

        return $methodSummaries;
    }

    /**
     * Get profit analysis
     * Compare revenue vs production cost
     */
    public function getProfitAnalysis(string $startDate, string $endDate): array
    {
        $sales = Sale::whereBetween('tgl_transaksi', [$startDate, $endDate])
            ->where('status', 'selesai')
            ->with('product')
            ->get();

        $totalRevenue = $sales->sum('total_harga');
        $totalCost = 0;
        $profitByProduct = [];

        foreach ($sales->groupBy('product_id') as $productId => $productSales) {
            $product = $productSales->first()->product;
            $totalQtySold = $productSales->sum('qty');
            $revenue = $productSales->sum('total_harga');

            // Get average production cost untuk product ini
            $avgProductionCost = $this->getAverageProductionCost($productId);
            $cost = $totalQtySold * $avgProductionCost;
            $profit = $revenue - $cost;

            $totalCost += $cost;

            $profitByProduct[] = [
                'product_name' => $product->nama_produk,
                'qty_sold' => $totalQtySold,
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => $profit,
                'margin_percent' => $cost > 0 ? round(($profit / $cost) * 100, 2) : 0,
            ];
        }

        $totalProfit = $totalRevenue - $totalCost;
        $overallMargin = $totalCost > 0 ? ($totalProfit / $totalCost) * 100 : 0;

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'overall' => [
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'total_profit' => $totalProfit,
                'margin_percent' => round($overallMargin, 2),
            ],
            'by_product' => $profitByProduct,
        ];
    }

    /**
     * Get average production cost untuk product
     * Dari production history
     */
    private function getAverageProductionCost(int $productId): float
    {
        // Get recent productions (last 10)
        $productions = DB::table('production')
            ->where('product_id', $productId)
            ->where('status', 'selesai')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($productions->isEmpty()) {
            // Fallback: estimate dari harga jual (asumsi margin 50%)
            $product = Product::find($productId);
            return $product ? $product->harga_jual * 0.5 : 0;
        }

        $totalCost = 0;
        $totalQty = 0;

        foreach ($productions as $production) {
            // Calculate cost dari formula
            $formulaDetails = DB::table('formula_details')
                ->where('formula_id', $production->formula_id)
                ->join('materials', 'materials.id', '=', 'formula_details.material_id')
                ->select('formula_details.qty', 'materials.harga')
                ->get();

            $costPerKg = $formulaDetails->sum(function($detail) {
                return $detail->qty * $detail->harga;
            });

            $totalCost += $costPerKg * $production->jumlah;
            $totalQty += $production->jumlah;
        }

        return $totalQty > 0 ? $totalCost / $totalQty : 0;
    }

    /**
     * ========================================
     * BEST SELLING PRODUCTS
     * ========================================
     */

    /**
     * Get best selling products
     */
    public function getBestSellingProducts(string $startDate, string $endDate, int $limit = 10): array
    {
        $sales = Sale::whereBetween('tgl_transaksi', [$startDate, $endDate])
            ->where('status', 'selesai')
            ->with('product')
            ->get()
            ->groupBy('product_id');

        $products = [];

        foreach ($sales as $productId => $productSales) {
            $product = $productSales->first()->product;
            
            $products[] = [
                'product_id' => $productId,
                'product_name' => $product->nama_produk,
                'category' => $product->kategori,
                'total_qty_sold' => $productSales->sum('qty'),
                'total_revenue' => $productSales->sum('total_harga'),
                'transaction_count' => $productSales->count(),
                'avg_qty_per_transaction' => round($productSales->sum('qty') / $productSales->count(), 2),
            ];
        }

        // Sort by qty sold
        usort($products, fn($a, $b) => $b['total_qty_sold'] <=> $a['total_qty_sold']);

        return array_slice($products, 0, $limit);
    }

    /**
     * Get sales trend (daily/weekly/monthly)
     */
    public function getSalesTrend(string $startDate, string $endDate, string $groupBy = 'daily'): array
    {
        $sales = Sale::whereBetween('tgl_transaksi', [$startDate, $endDate])
            ->where('status', 'selesai')
            ->select('tgl_transaksi', 'qty', 'total_harga')
            ->get();

        $trend = [];

        switch ($groupBy) {
            case 'daily':
                $grouped = $sales->groupBy('tgl_transaksi');
                foreach ($grouped as $date => $dateSales) {
                    $trend[] = [
                        'date' => $date,
                        'qty_sold' => $dateSales->sum('qty'),
                        'revenue' => $dateSales->sum('total_harga'),
                        'transaction_count' => $dateSales->count(),
                    ];
                }
                break;

            case 'weekly':
                $grouped = $sales->groupBy(function($sale) {
                    return \Carbon\Carbon::parse($sale->tgl_transaksi)->format('Y-W');
                });
                foreach ($grouped as $week => $weekSales) {
                    $trend[] = [
                        'week' => $week,
                        'qty_sold' => $weekSales->sum('qty'),
                        'revenue' => $weekSales->sum('total_harga'),
                        'transaction_count' => $weekSales->count(),
                    ];
                }
                break;

            case 'monthly':
                $grouped = $sales->groupBy(function($sale) {
                    return \Carbon\Carbon::parse($sale->tgl_transaksi)->format('Y-m');
                });
                foreach ($grouped as $month => $monthSales) {
                    $trend[] = [
                        'month' => $month,
                        'qty_sold' => $monthSales->sum('qty'),
                        'revenue' => $monthSales->sum('total_harga'),
                        'transaction_count' => $monthSales->count(),
                    ];
                }
                break;
        }

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'group_by' => $groupBy,
            ],
            'data' => $trend,
        ];
    }

    /**
     * ========================================
     * CUSTOMER ANALYSIS (Future Enhancement)
     * ========================================
     */

    /**
     * Get sales by customer segment
     * Note: Butuh tabel customers untuk implementasi full
     */
    public function getSalesByCustomerSegment(): array
    {
        // Placeholder untuk future enhancement
        // Saat ini bisa group by user_id (admin yang input)
        
        return [
            'message' => 'Feature ini butuh tabel customers untuk analisa lengkap',
            'suggestion' => 'Tambahkan field customer_id di tabel sales',
        ];
    }
}