<?php

namespace App\Services\Sales;

use App\Models\Sale;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Exception;

class SalesTransactionService
{
    /**
     * Proses transaksi penjualan
     */
    public function create(array $data): Sale
    {
        return DB::transaction(function () use ($data) {

            $this->validate($data);

            // 1️⃣ Simpan transaksi sale
            $sale = Sale::create([
                'sale_date'     => now(),
                'total_qty'     => $data['qty'],
                'total_amount' => $data['total_amount'],
                'payment_method' => $data['payment_method'],
                'notes'         => $data['notes'] ?? null,
            ]);

            // 2️⃣ Kurangi stok produk (FIFO)
            $this->consumeProductStock(
                productId: $data['product_id'],
                qty: $data['qty'],
                saleId: $sale->id
            );

            return $sale;
        });
    }

    /**
     * FIFO konsumsi stok produk
     */
    protected function consumeProductStock(int $productId, float $qty, int $saleId): void
    {
        $stocks = ProductStock::where('product_id', $productId)
            ->where('qty', '>', 0)
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        $remaining = $qty;

        foreach ($stocks as $stock) {
            if ($remaining <= 0) break;

            $used = min($stock->qty, $remaining);

            // Update stok batch
            $stock->decrement('qty', $used);

            // 3️⃣ Catat histori stok keluar
            StockMovement::create([
                'tipe'             => 'keluar',
                'sumber'           => 'sale',
                'qty'              => $used,
                'product_stock_id' => $stock->id,
                'ref_id'           => $saleId,
                'notes'            => 'Penjualan produk',
            ]);

            $remaining -= $used;
        }

        if ($remaining > 0) {
            throw new Exception('Stok produk tidak mencukupi');
        }
    }

    /**
     * Validasi domain penjualan
     */
    protected function validate(array $data): void
    {
        if (($data['qty'] ?? 0) <= 0) {
            throw new Exception('Qty penjualan harus lebih dari 0');
        }

        if (empty($data['product_id'])) {
            throw new Exception('Product ID wajib diisi');
        }

        if (empty($data['payment_method'])) {
            throw new Exception('Metode pembayaran wajib diisi');
        }
    }
}
