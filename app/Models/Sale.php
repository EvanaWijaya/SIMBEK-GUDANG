<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'qty',
        'total_harga',
        'metode_bayar',
        'status',
        'tgl_transaksi',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'total_harga' => 'decimal:2',
        'tgl_transaksi' => 'date',
    ];

    /**
     * Relationships
     */

    // Penjualan oleh user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Produk yang dijual
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scopes
     */

    // Penjualan selesai
    public function scopeSelesai($query)
    {
        return $query->where('status', 'selesai');
    }

    // Penjualan pending
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // Penjualan batal
    public function scopeBatal($query)
    {
        return $query->where('status', 'batal');
    }

    // Penjualan hari ini
    public function scopeToday($query)
    {
        return $query->whereDate('tgl_transaksi', today());
    }

    // Penjualan bulan ini
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('tgl_transaksi', now()->month)
                     ->whereYear('tgl_transaksi', now()->year);
    }

    // Penjualan dalam range tanggal
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('tgl_transaksi', [$startDate, $endDate]);
    }

    // Filter by metode bayar
    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('metode_bayar', $method);
    }

    /**
     * Accessors & Methods
     */

    // Check status
    public function isCompleted(): bool
    {
        return $this->status === 'selesai';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'batal';
    }

    // Format total harga
    public function getFormattedTotalAttribute(): string
    {
        return 'Rp ' . number_format((float) ($this->total_harga ?? 0), 2);
    }
}