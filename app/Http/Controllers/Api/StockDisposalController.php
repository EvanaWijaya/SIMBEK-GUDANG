<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockDisposal;
use App\Services\DisposalTransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StockDisposalController extends Controller
{
    protected DisposalTransactionService $disposalService;

    public function __construct(DisposalTransactionService $disposalService)
    {
        $this->disposalService = $disposalService;
    }

    /**
     * List stock disposals
     */
    public function index(Request $request)
    {
        $query = StockDisposal::with(['productStock.product', 'user']);

        if ($request->filled(['start_date', 'end_date'])) {
            $query->whereBetween('tgl_disposal', [
                $request->start_date,
                $request->end_date
            ]);
        }

        if ($request->filled('alasan')) {
            $query->where('alasan', $request->alasan);
        }

        return response()->json([
            'success' => true,
            'message' => 'Daftar disposal berhasil diambil',
            'data' => $query->orderByDesc('tgl_disposal')->paginate(10)
        ]);
    }

    /**
     * Create stock disposal
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_stock_id' => 'required|exists:product_stock,id',
            'alasan' => 'required|in:expired,rusak,hilang,lainnya',
            'tindakan' => 'nullable|string',
            'qty' => 'required|numeric|min:0.01',
            'tgl_disposal' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $disposal = $this->disposalService->create([
                ...$validator->validated(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Disposal stok berhasil dicatat',
                'data' => $disposal
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Delete disposal
     */
    public function destroy($id)
    {
        $disposal = StockDisposal::find($id);

        if (!$disposal) {
            return response()->json([
                'success' => false,
                'message' => 'Data disposal tidak ditemukan'
            ], 404);
        }

        $disposal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data disposal berhasil dihapus'
        ]);
    }
}
