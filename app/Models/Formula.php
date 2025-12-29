<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Formula extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'nama_formula',
        'catatan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relationships
     */

    // Formula untuk produk
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Detail komposisi formula
    public function details()
    {
        return $this->hasMany(FormulaDetail::class);
    }

    // Formula dipakai di produksi
    public function productions()
    {
        return $this->hasMany(Production::class);
    }

    /**
     * Scopes
     */

    // Hanya formula aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Formula untuk produk tertentu
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Methods
     */

    // Hitung kebutuhan material untuk qty tertentu
    public function calculateMaterialNeeds($quantity)
    {
        return $this->details->map(function ($detail) use ($quantity) {
            return [
                'material_id' => $detail->material_id,
                'material_name' => $detail->material->nama_material,
                'needed_qty' => $detail->qty * $quantity,
                'available_stock' => $detail->material->stok,
                'is_sufficient' => $detail->material->stok >= ($detail->qty * $quantity),
            ];
        });
    }

    // Validasi apakah stok mencukupi
    public function isStockSufficient($quantity): bool
    {
        foreach ($this->details as $detail) {
            $needed = $detail->qty * $quantity;
            if ($detail->material->stok < $needed) {
                return false;
            }
        }
        return true;
    }

    // Get total cost untuk produksi
    public function getProductionCost($quantity): float
    {
        $totalCost = 0;
        foreach ($this->details as $detail) {
            $totalCost += ($detail->qty * $quantity) * $detail->material->harga;
        }
        return $totalCost;
    }
}