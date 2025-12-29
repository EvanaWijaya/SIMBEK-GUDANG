<?php

namespace App\Services\Stock;

use App\Models\ProductStock;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ProductStockService extends BaseStockService
{
    /**
     * Tambah stok produk dari hasil produksi (1 batch = 1 row)
     */
    public function addStockFromProduction(
        int $productId,
        int $productionId,
        float $qty
    ): ProductStock {
        return $this->beginStockTransaction(function () use (
            $productId,
            $productionId,
            $qty
        ) {
            if ($qty <= 0) {
                throw new InvalidArgumentException('Qty produksi harus lebih dari 0');
            }

            // Simpan stok per batch
            $productStock = ProductStock::create([
                'product_id'    => $productId,
                'production_id' => $productionId,
                'qty'           => $qty,
            ]);

            // Catat stock movement (produk masuk)
            $this->recordStockMovement([
                'tipe'             => 'masuk',
                'sumber'           => 'produksi',
                'qty'              => $qty,
                'product_stock_id' => $productStock->id,
                'ref_id'           => $productionId,
            ]);

            return $productStock;
        });
    }

    /**
     * Kurangi stok produk (FIFO)
     * Digunakan untuk:
     * - penjualan
     * - pemakaian internal
     */
    public function reduceStock(
        int $productId,
        float $qty,
        string $source,
        ?int $refId = null
    ): void {
        $this->beginStockTransaction(function () use (
            $productId,
            $qty,
            $source,
            $refId
        ) {
            if ($qty <= 0) {
                throw new InvalidArgumentException('Qty harus lebih dari 0');
            }

            $allowedSources = ['penjualan', 'pemakaian_internal'];
            if (!in_array($source, $allowedSources)) {
                throw new InvalidArgumentException('Sumber stok tidak valid');
            }

            $availableStock = $this->getAvailableProductStock($productId);

            if ($availableStock->sum('qty') < $qty) {
                throw new InvalidArgumentException('Stok produk tidak mencukupi');
            }

            $remainingQty = $qty;

            foreach ($availableStock as $stock) {
                if ($remainingQty <= 0) {
                    break;
                }

                $deductQty = min($stock->qty, $remainingQty);

                // Kurangi stok batch
                $stock->decrement('qty', $deductQty);

                // Catat stock movement (produk keluar)
                $this->recordStockMovement([
                    'tipe'             => 'keluar',
                    'sumber'           => $source,
                    'qty'              => $deductQty,
                    'product_stock_id' => $stock->id,
                    'ref_id'           => $refId,
                ]);

                $remainingQty -= $deductQty;
            }
        });
    }

    /**
     * Wrapper khusus penjualan (opsional tapi rapi)
     */
    public function reduceStockForSale(
        int $productId,
        float $qty,
        int $salesId
    ): void {
        $this->reduceStock($productId, $qty, 'penjualan', $salesId);
    }

    /**
     * Ambil stok produk yang masih tersedia (FIFO)
     */
    protected function getAvailableProductStock(int $productId): Collection
    {
        return ProductStock::where('product_id', $productId)
            ->where('qty', '>', 0)
            ->orderBy('created_at') // FIFO
            ->lockForUpdate()
            ->get();
    }

    /**
     * Total stok produk (akumulasi semua batch)
     */
    public function getTotalStock(int $productId): float
    {
        return ProductStock::where('product_id', $productId)
            ->sum('qty');
    }
}
