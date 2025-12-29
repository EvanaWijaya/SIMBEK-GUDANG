<?php

namespace App\Services;

use App\Services\Stock\ProductStockService;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * ========================================
 * USAGE SERVICE - INTERNAL CONSUMPTION
 * ========================================
 * Pemakaian produk internal (pakan/obat)
 * untuk kambing/domba SIMBEK
 */
class UsageService
{
    protected ProductStockService $productStockService;

    public function __construct(ProductStockService $productStockService)
    {
        $this->productStockService = $productStockService;
    }

    /**
     * Record pemakaian produk internal
     */
    public function recordUsage(array $data): array
    {
        $this->validateUsageData($data);

        return DB::transaction(function () use ($data) {
            // Kurangi stok produk (FIFO)
            $this->productStockService->reduceStock(
                $data['product_id'],
                $data['qty'],
                'pemakaian_internal',
                null
            );

            $product = DB::table('products')->find($data['product_id']);

            // Catat activity log
            DB::table('activity_logs')->insert([
                'user_id'    => $data['user_id'],
                'aksi'       => 'product_usage',
                'catatan'    => json_encode([
                    'product_id'   => $data['product_id'],
                    'product_name' => $product->nama_produk,
                    'qty'          => $data['qty'],
                    'usage_type'   => $data['usage_type'],
                    'animal_type'  => $data['animal_type'],
                    'animal_ids'   => $data['animal_ids'] ?? null,
                    'notes'        => $data['notes'] ?? null,
                ]),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            return [
                'success'      => true,
                'product_id'   => $data['product_id'],
                'product_name' => $product->nama_produk,
                'qty_used'     => $data['qty'],
                'usage_type'   => $data['usage_type'],
                'animal_type'  => $data['animal_type'],
                'tgl_usage'    => $data['tgl_usage'] ?? now()->toDateString(),
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

        $allowedUsageTypes = ['feed', 'supplement', 'treatment'];
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
}
