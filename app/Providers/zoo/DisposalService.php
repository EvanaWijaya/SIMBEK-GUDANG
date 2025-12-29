<?php

namespace App\Services;

use App\Models\StockDisposal;
use App\Models\ProductStock;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * ========================================
 * DISPOSAL SERVICE - SIMBEK INVENTORY SYSTEM
 * ========================================
 * 
 * Service untuk disposal (pembuangan) produk
 * Handle: Expired, Rusak, Hilang
 * 
 * Workflow:
 * 1. Admin pilih product stock yang akan di-dispose
 * 2. Input qty, alasan, dan tindakan
 * 3. Deduct product stock
 * 4. Record disposal untuk audit
 * 5. Calculate financial loss
 * 
 * PENTING: Disposal = LOSS (kerugian)
 * Harus di-track dengan baik untuk financial reporting
 * 
 * Author: SIMBEK Team
 * Version: 1.0
 */
class DisposalService
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * ========================================
     * DISPOSAL TRANSACTION
     * ========================================
     */

    /**
     * Create disposal record
     * 
     * @param array $data [
     *   'product_stock_id' => int (batch yang di-dispose),
     *   'qty' => float (kg),
     *   'alasan' => string (expired/rusak/hilang/lainnya),
     *   'tindakan' => string (tindakan yang diambil),
     *   'tgl_disposal' => date,
     *   'user_id' => int (admin yang handle)
     * ]
     * @return StockDisposal
     */
    public function createDisposal(array $data): StockDisposal
    {
        // Validate
        $this->validateDisposalData($data);

        // Get product stock
        $productStock = ProductStock::with(['product', 'production'])
            ->findOrFail($data['product_stock_id']);

        // Validate qty tidak melebihi stock
        if ($data['qty'] > $productStock->qty) {
            throw new Exception(
                "Qty disposal ({$data['qty']} kg) melebihi stok tersedia ({$productStock->qty} kg)"
            );
        }

        return DB::transaction(function () use ($data, $productStock) {
            // 1. Create disposal record
            $disposal = StockDisposal::create([
                'product_stock_id' => $data['product_stock_id'],
                'qty' => $data['qty'],
                'alasan' => $data['alasan'],
                'tindakan' => $data['tindakan'] ?? null,
                'tgl_disposal' => $data['tgl_disposal'] ?? now()->format('Y-m-d'),
                'user_id' => $data['user_id'],
            ]);

            // 2. Deduct product stock
            $productStock->decrement('qty', $data['qty']);

            // 3. Record stock movement
            $this->stockService->recordMovement([
                'tipe' => 'keluar',
                'sumber' => 'disposal',
                'qty' => $data['qty'],
                'product_stock_id' => $data['product_stock_id'],
                'ref_id' => $disposal->id,
                'notes' => "Disposal: {$data['alasan']}",
            ]);

            return $disposal->fresh(['productStock.product', 'productStock.production', 'user']);
        });
    }

    /**
     * Validate disposal data
     */
    private function validateDisposalData(array $data): void
    {
        $required = ['product_stock_id', 'qty', 'alasan', 'user_id'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Field '{$field}' wajib diisi");
            }
        }

        if ($data['qty'] <= 0) {
            throw new Exception("Qty disposal harus lebih dari 0");
        }

        $allowedReasons = ['expired', 'rusak', 'hilang', 'lainnya'];
        if (!in_array(strtolower($data['alasan']), $allowedReasons)) {
            throw new Exception(
                "Alasan tidak valid. Pilihan: " . implode(', ', $allowedReasons)
            );
        }
    }

    /**
     * ========================================
     * BULK DISPOSAL (Untuk expired date check)
     * ========================================
     */

    /**
     * Get product stocks yang expired atau hampir expired
     */
    public function getExpiredStocks(int $daysThreshold = 0): array
    {
        $targetDate = now()->addDays($daysThreshold);

        $expiredStocks = ProductStock::with(['product', 'production'])
            ->where('qty', '>', 0)
            ->whereHas('production', function($query) use ($targetDate) {
                $query->where('expired_date', '<=', $targetDate);
            })
            ->get();

        $result = [];

        foreach ($expiredStocks as $stock) {
            $expiredDate = $stock->production->expired_date;
            $daysUntilExpired = now()->diffInDays($expiredDate, false);

            $result[] = [
                'product_stock_id' => $stock->id,
                'product_name' => $stock->product->nama_produk,
                'qty' => $stock->qty,
                'production_date' => $stock->production->tgl_produksi,
                'expired_date' => $expiredDate,
                'days_until_expired' => round($daysUntilExpired),
                'status' => $daysUntilExpired <= 0 ? 'expired' : 'near_expiry',
                'production_id' => $stock->production_id,
            ];
        }

        // Sort by expired date (paling urgent dulu)
        usort($result, function($a, $b) {
            return $a['days_until_expired'] <=> $b['days_until_expired'];
        });

        return $result;
    }

    /**
     * Bulk disposal untuk multiple product stocks sekaligus
     * Berguna untuk disposal expired stocks secara massal
     */
    public function bulkDisposal(array $items, int $userId): array
    {
        $results = [];

        DB::transaction(function () use ($items, $userId, &$results) {
            foreach ($items as $item) {
                try {
                    $disposal = $this->createDisposal([
                        'product_stock_id' => $item['product_stock_id'],
                        'qty' => $item['qty'],
                        'alasan' => $item['alasan'] ?? 'expired',
                        'tindakan' => $item['tindakan'] ?? 'Dibuang sesuai prosedur',
                        'tgl_disposal' => $item['tgl_disposal'] ?? now()->format('Y-m-d'),
                        'user_id' => $userId,
                    ]);

                    $results[] = [
                        'product_stock_id' => $item['product_stock_id'],
                        'status' => 'success',
                        'disposal_id' => $disposal->id,
                    ];
                } catch (Exception $e) {
                    $results[] = [
                        'product_stock_id' => $item['product_stock_id'],
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        return $results;
    }

    /**
     * ========================================
     * DISPOSAL ANALYSIS & REPORTS
     * ========================================
     */

    /**
     * Get disposal summary untuk periode tertentu
     */
    public function getDisposalSummary(string $startDate, string $endDate): array
    {
        $disposals = StockDisposal::whereBetween('tgl_disposal', [$startDate, $endDate])
            ->with(['productStock.product', 'productStock.production'])
            ->get();

        // Group by alasan
        $byReason = $disposals->groupBy('alasan');
        $reasonSummaries = [];

        foreach ($byReason as $reason => $reasonDisposals) {
            $totalQty = $reasonDisposals->sum('qty');
            $totalLoss = 0;

            foreach ($reasonDisposals as $disposal) {
                $loss = $this->calculateDisposalLoss($disposal);
                $totalLoss += $loss;
            }

            $reasonSummaries[] = [
                'alasan' => $reason,
                'alasan_label' => $this->getReasonLabel($reason),
                'count' => $reasonDisposals->count(),
                'total_qty' => $totalQty,
                'total_loss' => $totalLoss,
            ];
        }

        // Group by product
        $byProduct = [];
        foreach ($disposals as $disposal) {
            $productId = $disposal->productStock->product_id;
            
            if (!isset($byProduct[$productId])) {
                $byProduct[$productId] = [
                    'product_id' => $productId,
                    'product_name' => $disposal->productStock->product->nama_produk,
                    'total_qty' => 0,
                    'total_loss' => 0,
                    'count' => 0,
                ];
            }

            $byProduct[$productId]['total_qty'] += $disposal->qty;
            $byProduct[$productId]['total_loss'] += $this->calculateDisposalLoss($disposal);
            $byProduct[$productId]['count']++;
        }

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'summary' => [
                'total_disposals' => $disposals->count(),
                'total_qty_disposed' => $disposals->sum('qty'),
                'total_financial_loss' => $disposals->sum(function($d) {
                    return $this->calculateDisposalLoss($d);
                }),
            ],
            'by_reason' => $reasonSummaries,
            'by_product' => array_values($byProduct),
        ];
    }

    /**
     * Calculate financial loss dari disposal
     * Loss = qty × production cost per kg
     */
    private function calculateDisposalLoss(StockDisposal $disposal): float
    {
        $productStock = $disposal->productStock;
        $production = $productStock->production;

        // Get production cost dari formula
        $formulaDetails = DB::table('formula_details')
            ->where('formula_id', $production->formula_id)
            ->join('materials', 'materials.id', '=', 'formula_details.material_id')
            ->select('formula_details.qty', 'materials.harga')
            ->get();

        $costPerKg = $formulaDetails->sum(function($detail) {
            return $detail->qty * $detail->harga;
        });

        return $disposal->qty * $costPerKg;
    }

    /**
     * Get disposal trend
     */
    public function getDisposalTrend(string $startDate, string $endDate, string $groupBy = 'monthly'): array
    {
        $disposals = StockDisposal::whereBetween('tgl_disposal', [$startDate, $endDate])
            ->with('productStock')
            ->get();

        $trend = [];

        foreach ($disposals as $disposal) {
            $date = $disposal->tgl_disposal;
            
            switch ($groupBy) {
                case 'daily':
                    $key = $date->format('Y-m-d');
                    break;
                case 'weekly':
                    $key = $date->format('Y-W');
                    break;
                case 'monthly':
                    $key = $date->format('Y-m');
                    break;
                default:
                    $key = $date->format('Y-m');
            }

            if (!isset($trend[$key])) {
                $trend[$key] = [
                    'period' => $key,
                    'count' => 0,
                    'total_qty' => 0,
                    'total_loss' => 0,
                ];
            }

            $trend[$key]['count']++;
            $trend[$key]['total_qty'] += $disposal->qty;
            $trend[$key]['total_loss'] += $this->calculateDisposalLoss($disposal);
        }

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'group_by' => $groupBy,
            ],
            'data' => array_values($trend),
        ];
    }

    /**
     * Get disposal rate (disposal vs production)
     */
    public function getDisposalRate(string $startDate, string $endDate): array
    {
        // Total production
        $totalProduction = DB::table('production')
            ->whereBetween('tgl_produksi', [$startDate, $endDate])
            ->where('status', 'selesai')
            ->sum('jumlah');

        // Total disposal
        $disposals = StockDisposal::whereBetween('tgl_disposal', [$startDate, $endDate])->get();
        $totalDisposal = $disposals->sum('qty');

        $disposalRate = $totalProduction > 0 
            ? ($totalDisposal / $totalProduction) * 100 
            : 0;

        // By product
        $byProduct = [];
        foreach ($disposals->groupBy('productStock.product_id') as $productId => $productDisposals) {
            $productProduction = DB::table('production')
                ->where('product_id', $productId)
                ->whereBetween('tgl_produksi', [$startDate, $endDate])
                ->where('status', 'selesai')
                ->sum('jumlah');

            $productDisposalQty = $productDisposals->sum('qty');
            
            $productRate = $productProduction > 0 
                ? ($productDisposalQty / $productProduction) * 100 
                : 0;

            if ($productDisposals->first()->productStock) {
                $byProduct[] = [
                    'product_id' => $productId,
                    'product_name' => $productDisposals->first()->productStock->product->nama_produk,
                    'production_qty' => $productProduction,
                    'disposal_qty' => $productDisposalQty,
                    'disposal_rate' => round($productRate, 2),
                ];
            }
        }

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'overall' => [
                'total_production' => $totalProduction,
                'total_disposal' => $totalDisposal,
                'disposal_rate_percent' => round($disposalRate, 2),
                'status' => $this->getDisposalRateStatus($disposalRate),
            ],
            'by_product' => $byProduct,
        ];
    }

    /**
     * Determine disposal rate status
     */
    private function getDisposalRateStatus(float $rate): string
    {
        if ($rate <= 2) {
            return 'excellent'; // < 2% disposal = excellent
        } elseif ($rate <= 5) {
            return 'good'; // 2-5% = good
        } elseif ($rate <= 10) {
            return 'warning'; // 5-10% = need attention
        } else {
            return 'critical'; // > 10% = critical issue
        }
    }

    /**
     * ========================================
     * PREVENTION & RECOMMENDATIONS
     * ========================================
     */

    /**
     * Get recommendations untuk reduce disposal
     */
    public function getDisposalRecommendations(): array
    {
        $recommendations = [];

        // 1. Check for near-expiry stocks
        $nearExpiry = $this->getExpiredStocks(30); // 30 days threshold
        if (!empty($nearExpiry)) {
            $recommendations[] = [
                'type' => 'near_expiry',
                'priority' => 'high',
                'title' => 'Produk Hampir Expired',
                'description' => count($nearExpiry) . ' batch produk akan expired dalam 30 hari',
                'action' => 'Pertimbangkan diskon/promosi untuk mempercepat penjualan',
                'items' => $nearExpiry,
            ];
        }

        // 2. Check disposal rate
        $last30Days = $this->getDisposalRate(
            now()->subDays(30)->format('Y-m-d'),
            now()->format('Y-m-d')
        );

        if ($last30Days['overall']['disposal_rate_percent'] > 5) {
            $recommendations[] = [
                'type' => 'high_disposal_rate',
                'priority' => 'high',
                'title' => 'Tingkat Disposal Tinggi',
                'description' => "Disposal rate: {$last30Days['overall']['disposal_rate_percent']}% (30 hari terakhir)",
                'action' => 'Review proses produksi dan penyimpanan. Target ideal: < 5%',
            ];
        }

        // 3. Check production planning
        // Kalau ada produk yang sering disposal, mungkin produksi berlebihan
        $highDisposalProducts = array_filter(
            $last30Days['by_product'], 
            fn($p) => $p['disposal_rate'] > 10
        );

        if (!empty($highDisposalProducts)) {
            $recommendations[] = [
                'type' => 'overproduction',
                'priority' => 'medium',
                'title' => 'Potensi Overproduksi',
                'description' => count($highDisposalProducts) . ' produk memiliki disposal rate > 10%',
                'action' => 'Kurangi jumlah produksi atau tingkatkan penjualan untuk produk ini',
                'products' => array_column($highDisposalProducts, 'product_name'),
            ];
        }

        return $recommendations;
    }

    /**
     * Helper: Get reason label
     */
    private function getReasonLabel(string $reason): string
    {
        return match(strtolower($reason)) {
            'expired' => 'Kadaluarsa',
            'rusak' => 'Rusak/Cacat',
            'hilang' => 'Hilang',
            'lainnya' => 'Lainnya',
            default => ucfirst($reason),
        };
    }
}