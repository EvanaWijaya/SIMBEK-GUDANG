<?php

namespace App\Services;

use App\Models\StockDisposal;
use App\Models\ProductStock;
use App\Services\ActivityLogService;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * ========================================
 * DISPOSAL TRANSACTION SERVICE
 * ========================================
 *
 * Handle transaksi disposal produk (LOSS)
 * Konsisten dengan:
 * - stock_disposal migration
 * - StockDisposal model
 * - StockDisposalController
 *
 * Author: SIMBEK Team
 * Version: 3.0 (Refactored)
 */
class DisposalTransactionService
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    /**
     * Create disposal transaction
     *
     * @param array $data
     * @return StockDisposal
     * @throws Exception
     */
    public function create(array $data): StockDisposal
    {
        $this->validate($data);

        return DB::transaction(function () use ($data) {

            $productStock = ProductStock::with('product')
                ->lockForUpdate()
                ->findOrFail($data['product_stock_id']);

            if ($productStock->qty < $data['qty']) {
                throw new Exception(
                    "Stok {$productStock->product->nama} tidak mencukupi"
                );
            }

            // 1️⃣ Create disposal
            $disposal = StockDisposal::create([
                'product_stock_id' => $data['product_stock_id'],
                'qty'             => $data['qty'],
                'alasan'          => $data['alasan'],
                'tindakan'        => $data['tindakan'] ?? null,
                'tgl_disposal'    => $data['tgl_disposal'] ?? now()->toDateString(),
                'user_id'         => $data['user_id'],
            ]);

            // 2️⃣ Reduce product stock
            $productStock->decrement('qty', $data['qty']);

            // 3️⃣ Stock movement
            StockMovement::createProductMovement(
                $productStock->id,
                'out',
                'disposal',
                $data['qty'],
                $disposal->id
            );

            // 4️⃣ Activity log
            $this->activityLogService->log(
                auth()->id(),
                "Disposal produk {$productStock->product->nama} sebanyak {$data['qty']} ({$data['alasan']})",
                request()
            );

            return $disposal->load(['productStock.product', 'user']);
        });
    }

    /**
     * Bulk disposal (mass disposal)
     */
    public function bulk(array $items, int $userId): array
    {
        $results = [];

        foreach ($items as $item) {
            try {
                $disposal = $this->create([
                    'product_stock_id' => $item['product_stock_id'],
                    'qty' => $item['qty'],
                    'alasan' => $item['alasan'] ?? 'expired',
                    'tindakan' => $item['tindakan'] ?? 'Dibuang sesuai prosedur',
                    'tgl_disposal' => $item['tgl_disposal'] ?? now()->toDateString(),
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

        return $results;
    }

    /**
     * Validate disposal data
     */
    private function validate(array $data): void
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
        if (!in_array($data['alasan'], $allowedReasons)) {
            throw new Exception(
                "Alasan tidak valid. Pilihan: " . implode(', ', $allowedReasons)
            );
        }
    }
}
