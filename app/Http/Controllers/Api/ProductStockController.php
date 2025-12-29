<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductStock;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ProductStockController extends Controller
{
    /**
     * Get all product stocks
     */
    public function index(Request $request)
    {
        $query = ProductStock::with(['product', 'production']);

        // Filter by product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by availability
        if ($request->has('available') && $request->available == true) {
            $query->where('qty', '>', 0);
        }

        $stocks = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Product stocks retrieved successfully',
            'data' => $stocks
        ], 200);
    }

    /**
     * Get single product stock
     */
    public function show($id)
    {
        $stock = ProductStock::with(['product', 'production', 'sales', 'disposals'])->find($id);

        if (!$stock) {
            return response()->json([
                'success' => false,
                'message' => 'Product stock not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product stock retrieved successfully',
            'data' => $stock
        ], 200);
    }

    /**
     * Update product stock quantity
     */
    public function update(Request $request, $id)
    {
        $stock = ProductStock::find($id);

        if (!$stock) {
            return response()->json([
                'success' => false,
                'message' => 'Product stock not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'qty' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $stock->update(['qty' => $request->qty]);

        return response()->json([
            'success' => true,
            'message' => 'Product stock updated successfully',
            'data' => $stock
        ], 200);
    }

    /**
     * Get stock summary/statistics
     */
    public function summary()
    {
        $totalProducts = Product::count();
        $totalStockQty = ProductStock::sum('qty');
        
        // Calculate total value (qty * harga_jual from product)
        $totalValue = ProductStock::join('products', 'product_stock.product_id', '=', 'products.id')
            ->selectRaw('SUM(product_stock.qty * products.harga_jual) as total_value')
            ->first()
            ->total_value ?? 0;

        $lowStock = ProductStock::where('qty', '<', 10)->count();

        // Stock by product
        $stockByProduct = ProductStock::with('product')
            ->select('product_id', DB::raw('SUM(qty) as total_qty'))
            ->groupBy('product_id')
            ->orderBy('total_qty', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Stock summary retrieved successfully',
            'data' => [
                'total_products' => $totalProducts,
                'total_stock_quantity' => $totalStockQty,
                'total_stock_value' => $totalValue,
                'low_stock_items' => $lowStock,
                'stock_by_product' => $stockByProduct,
            ]
        ], 200);
    }

    /**
     * Get stocks grouped by product
     */
    public function byProduct()
    {
        $stocksByProduct = Product::with(['productStocks' => function($query) {
            $query->where('qty', '>', 0);
        }])->get()->map(function($product) {
            $totalQty = $product->productStocks->sum('qty');
            return [
                'product_id' => $product->id,
                'kode_produk' => $product->kode_produk,
                'nama_produk' => $product->nama_produk,
                'kategori' => $product->kategori,
                'satuan' => $product->satuan,
                'harga_jual' => $product->harga_jual,
                'total_qty' => $totalQty,
                'total_value' => $totalQty * $product->harga_jual,
                'stocks' => $product->productStocks,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Stocks by product retrieved successfully',
            'data' => $stocksByProduct
        ], 200);
    }
}