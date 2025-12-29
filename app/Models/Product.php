<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode_produk',
        'nama_produk',
        'kategori',
        'satuan',
        'harga_jual',
        'deskripsi',
    ];

    protected $casts = [
        'harga_jual' => 'decimal:2',
    ];

    /**
     * Relationships
     */

    // Produk punya banyak formula
    public function formulas()
    {
        return $this->hasMany(Formula::class);
    }

    // Formula yang aktif
    public function activeFormula()
    {
        return $this->hasOne(Formula::class)->where('is_active', true);
    }

    // Produk diproduksi
    public function productions()
    {
        return $this->hasMany(Production::class);
    }

    // Stok produk
    public function productStocks()
    {
        return $this->hasMany(ProductStock::class);
    }

    // Penjualan produk
    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Scopes
     */

    // Filter by kategori
    public function scopeByKategori($query, $kategori)
    {
        return $query->where('kategori', $kategori);
    }

    // Produk dengan stok tersedia
    public function scopeWithAvailableStock($query)
    {
        return $query->withSum('productStocks as total_stock', 'qty')
                     ->having('total_stock', '>', 0);
    }

    /**
     * Accessors & Methods
     */

    // Total stok tersedia
    public function getTotalStockAttribute(): float
    {
        return $this->productStocks()->sum('qty');
    }

    // Check apakah produk ada stok
    public function hasStock(): bool
    {
        return $this->total_stock > 0;
    }

    // Format harga jual
    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format((float) ($this->harga_jual ?? 0), 2);
    }

    // Get total nilai stok
    public function getStockValueAttribute(): float
    {
        return $this->total_stock * $this->harga_jual;
    }

    public function planning()
    {
        return $this->hasOne(ProductPlanning::class);
    }

}

