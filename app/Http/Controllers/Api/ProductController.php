<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPlanning;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    protected ActivityLogService $activityLog;

    public function __construct(ActivityLogService $activityLog)
    {
        $this->activityLog = $activityLog;
    }

    /**
     * List products (with planning)
     */
    public function index(Request $request)
    {
        $products = Product::with('planning')
            ->orderBy('nama', 'asc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Daftar produk berhasil diambil',
            'data' => $products
        ]);
    }

    /**
     * Show single product
     */
    public function show($id)
    {
        $product = Product::with('planning')->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail produk berhasil diambil',
            'data' => $product
        ]);
    }

    /**
     * Store new product + planning
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:products,nama'],
            'deskripsi' => ['nullable', 'string'],
            'stok_min' => ['required', 'numeric', 'min:0'],
            'lead_time_days' => ['required', 'integer', 'min:0'],
            'safety_stock' => ['required', 'numeric', 'min:0'],
        ]);

        DB::beginTransaction();
        try {
            $product = Product::create([
                'nama' => $validated['nama'],
                'deskripsi' => $validated['deskripsi'] ?? null,
            ]);

            ProductPlanning::create([
                'product_id' => $product->id,
                'stok_min' => $validated['stok_min'],
                'lead_time_days' => $validated['lead_time_days'],
                'safety_stock' => $validated['safety_stock'],
            ]);

            DB::commit();

            // ✅ ACTIVITY LOG
            $this->activityLog->log(
                auth()->id(),
                'create_product',
                [
                    'product_id' => $product->id,
                    'nama' => $product->nama
                ],
            );

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil dibuat',
                'data' => $product->load('planning')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update product + planning
     */
    public function update(Request $request, $id)
    {
        $product = Product::with('planning')->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:products,nama,' . $product->id],
            'deskripsi' => ['nullable', 'string'],
            'stok_min' => ['required', 'numeric', 'min:0'],
            'lead_time_days' => ['required', 'integer', 'min:0'],
            'safety_stock' => ['required', 'numeric', 'min:0'],
        ]);

        DB::beginTransaction();
        try {
            $product->update([
                'nama' => $validated['nama'],
                'deskripsi' => $validated['deskripsi'] ?? null,
            ]);

            $product->planning()->update([
                'stok_min' => $validated['stok_min'],
                'lead_time_days' => $validated['lead_time_days'],
                'safety_stock' => $validated['safety_stock'],
            ]);

            DB::commit();

            // ✅ ACTIVITY LOG
            $this->activityLog->log(
                auth()->id(),
                'update_product',
                [
                    'product_id' => $product->id,
                    'nama' => $product->nama
                ],
            );

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil diperbarui',
                'data' => $product->fresh()->load('planning')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete product
     */
    public function destroy(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $product->planning()->delete();
            $product->delete();

            DB::commit();

            // ✅ ACTIVITY LOG
            $this->activityLog->log(
                auth()->id(),
                'delete_product',
                [
                    'product_id' => $id,
                    'nama' => $product->nama
                ],
            );

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Produk tidak bisa dihapus karena sudah digunakan'
            ], 400);
        }
    }
}
