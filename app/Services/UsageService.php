<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Exception;

/**
 * ========================================
 * USAGE SERVICE - INTERNAL CONSUMPTION
 * ========================================
 * 
 * Service untuk pemakaian produk internal
 * Digunakan untuk tracking pakan/obat yang dipakai
 * untuk kambing/domba SIMBEK sendiri (bukan dijual)
 * 
 * Workflow:
 * 1. Admin input pemakaian (feed/treat kambing)
 * 2. Deduct product stock
 * 3. Record usage untuk cost tracking
 * 
 * PERBEDAAN dengan SalesService:
 * - Sales = Transaksi jual ke user (ada revenue)
 * - Usage = Konsumsi internal (pure cost, no revenue)
 * 
 * Author: SIMBEK Team
 * Version: 1.0
 */
class UsageService
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * ========================================
     * USAGE TRANSACTION
     * ========================================
     */

    /**
     * Record pemakaian produk untuk kambing/domba SIMBEK
     * 
     * @param array $data [
     *   'product_id' => int (pakan/obat yang dipakai),
     *   'qty' => float (kg),
     *   'usage_type' => string (feed/treatment/supplement),
     *   'animal_type' => string (kambing/domba),
     *   'animal_ids' => array (optional: ID kambing/domba yang diberi),
     *   'notes' => string (optional: catatan tambahan),
     *   'user_id' => int (admin yang input),
     *   'tgl_usage' => date
     * ]
     * @return array
     */
    public function recordUsage(array $data): array
    {
        // Validate
        $this->validateUsageData($data);

        // Check stock availability
        if (!$this->stockService->checkProductAvailability($data['product_id'], $data['qty'])) {
            $availableStock = DB::table('product_stock')
                ->where('product_id', $data['product_id'])
                ->sum('qty');
                
            throw new Exception(
                "Stok produk tidak mencukupi. " .
                "Tersedia: {$availableStock} kg, Dibutuhkan: {$data['qty']} kg"
            );
        }

        return DB::transaction(function () use ($data) {
            // 1. Deduct product stock (FIFO)
            $this->stockService->deductProductStock(
                $data['product_id'],
                $data['qty'],
                'usage',
                null // No reference ID untuk usage
            );

            // 2. Record di activity log untuk tracking
            // (Kita tidak buat tabel product_usage terpisah untuk simplicity)
            // Cukup record di stock_movements + activity_logs
            
            $product = DB::table('products')->find($data['product_id']);
            
            DB::table('activity_logs')->insert([
                'user_id' => $data['user_id'],
                'aksi' => 'product_usage',
                'catatan' => json_encode([
                    'product_id' => $data['product_id'],
                    'product_name' => $product->nama_produk,
                    'qty' => $data['qty'],
                    'usage_type' => $data['usage_type'],
                    'animal_type' => $data['animal_type'],
                    'animal_ids' => $data['animal_ids'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            return [
                'success' => true,
                'product_id' => $data['product_id'],
                'product_name' => $product->nama_produk,
                'qty_used' => $data['qty'],
                'usage_type' => $data['usage_type'],
                'animal_type' => $data['animal_type'],
                'tgl_usage' => $data['tgl_usage'],
            ];
        });
    }

    /**
     * Validate usage data
     */
    private function validateUsageData(array $data): void
    {
        $required = ['product_id', 'qty', 'usage_type', 'animal_type', 'user_id'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Field '{$field}' wajib diisi");
            }
        }

        if ($data['qty'] <= 0) {
            throw new Exception("Jumlah pemakaian harus lebih dari 0");
        }

        $allowedUsageTypes = ['feed', 'treatment', 'supplement'];
        if (!in_array(strtolower($data['usage_type']), $allowedUsageTypes)) {
            throw new Exception(
                "Usage type tidak valid. Pilihan: " . implode(', ', $allowedUsageTypes)
            );
        }

        $allowedAnimalTypes = ['kambing', 'domba'];
        if (!in_array(strtolower($data['animal_type']), $allowedAnimalTypes)) {
            throw new Exception(
                "Animal type tidak valid. Pilihan: " . implode(', ', $allowedAnimalTypes)
            );
        }
    }

    /**
     * ========================================
     * USAGE ANALYSIS & REPORTS
     * ========================================
     */

    /**
     * Get usage summary untuk periode tertentu
     */
    public function getUsageSummary(string $startDate, string $endDate): array
    {
        // Get usage dari activity logs
        $usageLogs = DB::table('activity_logs')
            ->where('aksi', 'product_usage')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $summary = [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'total_usage_transactions' => $usageLogs->count(),
            'by_product' => [],
            'by_animal_type' => [
                'kambing' => 0,
                'domba' => 0,
            ],
            'by_usage_type' => [
                'feed' => 0,
                'treatment' => 0,
                'supplement' => 0,
            ],
            'total_cost' => 0,
        ];

        $productUsage = [];

        foreach ($usageLogs as $log) {
            $data = json_decode($log->catatan, true);
            
            // Group by product
            $productId = $data['product_id'];
            if (!isset($productUsage[$productId])) {
                $productUsage[$productId] = [
                    'product_id' => $productId,
                    'product_name' => $data['product_name'],
                    'total_qty' => 0,
                    'transaction_count' => 0,
                ];
            }
            
            $productUsage[$productId]['total_qty'] += $data['qty'];
            $productUsage[$productId]['transaction_count']++;

            // Count by animal type
            $animalType = strtolower($data['animal_type']);
            if (isset($summary['by_animal_type'][$animalType])) {
                $summary['by_animal_type'][$animalType]++;
            }

            // Count by usage type
            $usageType = strtolower($data['usage_type']);
            if (isset($summary['by_usage_type'][$usageType])) {
                $summary['by_usage_type'][$usageType]++;
            }
        }

        // Calculate cost per product
        foreach ($productUsage as &$product) {
            $productData = DB::table('products')->find($product['product_id']);
            $product['cost'] = $product['total_qty'] * $productData->harga_jual;
            $summary['total_cost'] += $product['cost'];
        }

        $summary['by_product'] = array_values($productUsage);

        return $summary;
    }

    /**
     * Get usage trend (daily/weekly/monthly)
     */
    public function getUsageTrend(string $startDate, string $endDate, string $groupBy = 'daily'): array
    {
        $usageLogs = DB::table('activity_logs')
            ->where('aksi', 'product_usage')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $trend = [];

        foreach ($usageLogs as $log) {
            $data = json_decode($log->catatan, true);
            $date = date('Y-m-d', strtotime($log->created_at));
            
            switch ($groupBy) {
                case 'daily':
                    $key = $date;
                    break;
                case 'weekly':
                    $key = date('Y-W', strtotime($date));
                    break;
                case 'monthly':
                    $key = date('Y-m', strtotime($date));
                    break;
                default:
                    $key = $date;
            }

            if (!isset($trend[$key])) {
                $trend[$key] = [
                    'period' => $key,
                    'total_qty' => 0,
                    'transaction_count' => 0,
                ];
            }

            $trend[$key]['total_qty'] += $data['qty'];
            $trend[$key]['transaction_count']++;
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
     * Get cost analysis untuk internal consumption
     * Berguna untuk calculate cost of goods (kambing/domba)
     */
    public function getCostAnalysis(string $startDate, string $endDate): array
    {
        $summary = $this->getUsageSummary($startDate, $endDate);

        // Calculate cost per animal (assuming equal distribution)
        $totalAnimals = DB::table('kambing')->count() + DB::table('domba')->count();
        
        $costPerAnimal = $totalAnimals > 0 
            ? $summary['total_cost'] / $totalAnimals 
            : 0;

        return [
            'period' => $summary['period'],
            'total_internal_cost' => $summary['total_cost'],
            'total_animals' => $totalAnimals,
            'cost_per_animal' => round($costPerAnimal, 2),
            'usage_breakdown' => $summary['by_product'],
        ];
    }
}