<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Sales\SalesTransactionService;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Exception;

class SaleController extends Controller
{
    protected SalesTransactionService $salesService;
    protected ActivityLogService $activityLogService;


    public function __construct(
        SalesTransactionService $salesService,
        ActivityLogService $activityLogService
    ) {
        $this->salesService = $salesService;
        $this->activityLogService = $activityLogService;
    }

    /**
     * Simpan transaksi penjualan
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'tanggal' => ['required', 'date'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
                'items.*.qty' => ['required', 'numeric', 'min:0.01'],
                'payment_method' => ['required', 'string'],
                'notes' => ['nullable', 'string'],
            ]);

            $sale = $this->salesService->create($validated);

            $this->activityLogService->log(
                auth()->id(),
                'create',
                'Transaksi penjualan dibuat'
            );

            return response()->json([
                'success' => true,
                'message' => 'Transaksi penjualan berhasil disimpan',
                'data' => [
                        'sale_id' => $sale->id,
                        'tanggal' => $sale->tanggal,
                        'total_qty' => $sale->total_qty,
                    ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses transaksi penjualan',
            ], 500);
        }
    }
}