<?php

namespace App\Services\Core;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class BaseService
{
    /**
     * Jalankan proses bisnis dalam DB Transaction
     */
    protected function transaction(callable $callback)
    {
        return DB::transaction(function () use ($callback) {
            return $callback();
        });
    }

    /**
     * Response standar sukses
     */
    protected function success(string $message, $data = null)
    {
        return [
            'success' => true,
            'message' => $message,
            'data'    => $data
        ];
    }

    /**
     * Response standar error
     */
    protected function error(string $message, Throwable $e = null)
    {
        if ($e) {
            Log::error($message, [
                'exception' => $e
            ]);
        }

        return [
            'success' => false,
            'message' => $message
        ];
    }

    /**
     * Validasi quantity (dipakai di stock, production, disposal)
     */
    protected function validateQuantity(float|int $qty)
    {
        if ($qty <= 0) {
            throw new \Exception('Quantity harus lebih dari 0');
        }
    }

    /**
     * Validasi stok cukup
     */
    protected function ensureStockAvailable(float $available, float $required)
    {
        if ($available < $required) {
            throw new \Exception('Stok tidak mencukupi');
        }
    }
}
