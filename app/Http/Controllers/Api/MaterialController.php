<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class MaterialController extends Controller
{
    /**
     * List material (pagination)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Material::query();

        // Optional filter
        if ($request->has('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        if ($request->has('low_stock')) {
            $query->whereColumn('stok', '<=', 'stok_min');
        }

        $materials = $query
            ->orderBy('nama_material')
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'message' => 'Daftar material berhasil diambil',
            'data' => $materials,
        ]);
    }

    /**
     * Show detail material
     */
    public function show(int $id): JsonResponse
    {
        $material = Material::find($id);

        if (!$material) {
            return response()->json([
                'success' => false,
                'message' => 'Material tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail material',
            'data' => $material,
        ]);
    }

    /**
     * Create material baru
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'kategori'         => 'required|string|max:100',
            'nama_material'    => 'required|string|max:255|unique:materials,nama_material',
            'satuan'           => 'required|string|max:20',
            'stok_min'         => 'required|numeric|min:0',
            'lead_time_days'   => 'required|integer|min:0',
            'safety_stock'     => 'required|numeric|min:0',
            'harga'            => 'required|numeric|min:0',
            'supplier'         => 'nullable|string|max:255',
            'expired_date'     => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $material = Material::create([
            'kategori'       => $request->kategori,
            'nama_material'  => $request->nama_material,
            'satuan'         => $request->satuan,
            'stok'           => 0, // ⬅️ stok awal selalu 0 (AMAN)
            'stok_min'       => $request->stok_min,
            'lead_time_days' => $request->lead_time_days,
            'safety_stock'   => $request->safety_stock,
            'harga'          => $request->harga,
            'supplier'       => $request->supplier,
            'expired_date'   => $request->expired_date,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Material berhasil dibuat',
            'data' => $material,
        ], 201);
    }

    /**
     * Update material (TIDAK untuk update stok)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $material = Material::find($id);

        if (!$material) {
            return response()->json([
                'success' => false,
                'message' => 'Material tidak ditemukan',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'kategori'         => 'sometimes|string|max:100',
            'nama_material'    => 'sometimes|string|max:255|unique:materials,nama_material,' . $id,
            'satuan'           => 'sometimes|string|max:20',
            'stok_min'         => 'sometimes|numeric|min:0',
            'lead_time_days'   => 'sometimes|integer|min:0',
            'safety_stock'     => 'sometimes|numeric|min:0',
            'harga'            => 'sometimes|numeric|min:0',
            'supplier'         => 'nullable|string|max:255',
            'expired_date'     => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        // ⛔ stok tidak boleh diupdate lewat sini
        $material->update($request->except(['stok']));

        return response()->json([
            'success' => true,
            'message' => 'Material berhasil diperbarui',
            'data' => $material,
        ]);
    }

    /**
     * Delete material (AMAN)
     */
    public function destroy(int $id): JsonResponse
    {
        $material = Material::find($id);

        if (!$material) {
            return response()->json([
                'success' => false,
                'message' => 'Material tidak ditemukan',
            ], 404);
        }

        if ($material->stok > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Material tidak bisa dihapus karena masih memiliki stok',
            ], 400);
        }

        $material->delete();

        return response()->json([
            'success' => true,
            'message' => 'Material berhasil dihapus',
        ]);
    }
}
