<?php

namespace App\Services\Stock;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use App\Models\StockMovement;
use Carbon\Carbon;

abstract class BaseStockService
{
    /**
     * Bungkus seluruh operasi stok dalam 1 transaksi DB
     */
    protected function beginStockTransaction(callable $callback)
    {
        return DB::transaction(function () use ($callback) {
            return $callback();
        });
    }

    /**
     * Validasi dasar data stock movement
     *
     * Aturan keras:
     * - qty > 0
     * - tipe hanya 'masuk' / 'keluar'
     * - HARUS material_id XOR product_stock_id
     */
    protected function validateMovementData(array $data): void
    {
        $validator = Validator::make($data, [
            'tipe'             => 'required|in:masuk,keluar',
            'sumber'           => 'required|string',
            'qty'              => 'required|numeric|min:0.01',
            'material_id'      => 'nullable|integer',
            'product_stock_id' => 'nullable|integer',
            'ref_id'           => 'nullable|integer',
            'notes'            => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $hasMaterial = !empty($data['material_id']);
        $hasProduct  = !empty($data['product_stock_id']);

        if ($hasMaterial === $hasProduct) {
            throw new InvalidArgumentException(
                'Stock movement harus memiliki SALAH SATU: material_id ATAU product_stock_id'
            );
        }
    }

    /**
     * Satu-satunya pintu untuk mencatat stock movement
     */
    protected function recordStockMovement(array $data): StockMovement
    {
        $this->validateMovementData($data);

        return StockMovement::create([
            'tipe'             => $data['tipe'],
            'sumber'           => $data['sumber'],
            'qty'              => $data['qty'],
            'material_id'      => $data['material_id'] ?? null,
            'product_stock_id' => $data['product_stock_id'] ?? null,
            'ref_id'           => $data['ref_id'] ?? null,
            'notes'            => $data['notes'] ?? null,
        ]);
    }

    /**
     * Validasi stok mencukupi sebelum dikurangi
     */
    protected function assertStockSufficient(Model $model, float $qty): void
    {
        if (!isset($model->stok) && !isset($model->qty)) {
            throw new InvalidArgumentException('Model tidak memiliki field stok / qty');
        }

        $currentStock = $model->stok ?? $model->qty;

        if ($currentStock < $qty) {
            throw new InvalidArgumentException('Stok tidak mencukupi');
        }
    }

    /**
     * Tambah stok (material / product_stock)
     */
    protected function increaseStock(Model $model, float $qty): void
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty harus lebih dari 0');
        }

        if (isset($model->stok)) {
            $model->stok += $qty;
        } elseif (isset($model->qty)) {
            $model->qty += $qty;
        } else {
            throw new InvalidArgumentException('Model tidak memiliki field stok / qty');
        }

        $model->save();
    }

    /**
     * Kurangi stok (material / product_stock)
     */
    protected function decreaseStock(Model $model, float $qty): void
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty harus lebih dari 0');
        }

        $this->assertStockSufficient($model, $qty);

        if (isset($model->stok)) {
            $model->stok -= $qty;
        } elseif (isset($model->qty)) {
            $model->qty -= $qty;
        } else {
            throw new InvalidArgumentException('Model tidak memiliki field stok / qty');
        }

        $model->save();
    }

    /**
     * Hitung rata-rata pemakaian harian material
     * (dipakai untuk ROP, TIDAK kirim notifikasi di sini)
     */
    protected function calculateAverageDailyUsage(
        int $materialId,
        int $days = 30
    ): float {
        $startDate = Carbon::now()->subDays($days);

        $totalUsed = StockMovement::where('material_id', $materialId)
            ->where('tipe', 'keluar')
            ->where('created_at', '>=', $startDate)
            ->sum('qty');

        if ($totalUsed <= 0) {
            return 0;
        }

        return $totalUsed / $days;
    }

    /**
     * Hitung Reorder Point (ROP)
     */
    protected function calculateROP(
        float $averageDailyUsage,
        int $leadTimeDays,
        float $safetyStock
    ): float {
        return ($averageDailyUsage * $leadTimeDays) + ($safetyStock ?? 0);
    }
}
