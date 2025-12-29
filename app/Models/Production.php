<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Production extends Model
{
    use HasFactory;

    protected $table = 'production';

    protected $fillable = [
        'product_id',
        'formula_id',
        'tgl_produksi',
        'jumlah',
        'satuan',
        'expired_date',
        'status',
        'user_id',
    ];

    protected $casts = [
        'tgl_produksi' => 'date',
        'expired_date' => 'date',
        'jumlah' => 'decimal:2',
    ];

    /**
     * Relationships
     */

    // Produksi produk apa
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Menggunakan formula apa
    public function formula()
    {
        return $this->belongsTo(Formula::class);
    }

    // User yang melakukan produksi
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Produksi menghasilkan product stock
    public function productStocks()
    {
        return $this->hasMany(ProductStock::class);
    }

    /**
     * Scopes
     */

    // Produksi selesai
    public function scopeSelesai($query)
    {
        return $query->where('status', 'selesai');
    }

    // Produksi pending
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // Produksi batal
    public function scopeBatal($query)
    {
        return $query->where('status', 'batal');
    }

    // Produksi hari ini
    public function scopeToday($query)
    {
        return $query->whereDate('tgl_produksi', today());
    }

    // Produksi bulan ini
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('tgl_produksi', now()->month)
                     ->whereYear('tgl_produksi', now()->year);
    }

    // Produksi dalam range tanggal
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('tgl_produksi', [$startDate, $endDate]);
    }

    /**
     * Accessors & Methods
     */

    // Check apakah produksi sudah selesai
    public function isCompleted(): bool
    {
        return $this->status === 'selesai';
    }

    // Check apakah produksi pending
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    // Check apakah produksi dibatalkan
    public function isCancelled(): bool
    {
        return $this->status === 'batal';
    }

    // Get total stok dari produksi ini
    public function getTotalStockAttribute(): float
    {
        return $this->productStocks()->sum('qty');
    }
}