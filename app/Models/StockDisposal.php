<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockDisposal extends Model
{
    use HasFactory;

    protected $table = 'stock_disposal';

    protected $fillable = [
        'product_stock_id',
        'qty',
        'alasan',
        'tindakan',
        'tgl_disposal',
        'user_id',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'tgl_disposal' => 'date',
    ];

    /**
     * Relationships
     */

    // Disposal untuk product stock
    public function productStock()
    {
        return $this->belongsTo(ProductStock::class);
    }

    // User yang melakukan disposal
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */

    // Filter by alasan
    public function scopeByAlasan($query, $alasan)
    {
        return $query->where('alasan', $alasan);
    }

    // Disposal expired
    public function scopeExpired($query)
    {
        return $query->where('alasan', 'expired');
    }

    // Disposal rusak
    public function scopeRusak($query)
    {
        return $query->where('alasan', 'rusak');
    }

    // Disposal hilang
    public function scopeHilang($query)
    {
        return $query->where('alasan', 'hilang');
    }

    // Disposal hari ini
    public function scopeToday($query)
    {
        return $query->whereDate('tgl_disposal', today());
    }

    // Disposal bulan ini
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('tgl_disposal', now()->month)
                     ->whereYear('tgl_disposal', now()->year);
    }

    // Disposal dalam range tanggal
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('tgl_disposal', [$startDate, $endDate]);
    }

    /**
     * Accessors
     */

    // Get alasan label
    public function getAlasanLabelAttribute(): string
    {
        return match($this->alasan) {
            'expired' => 'Kadaluarsa',
            'rusak' => 'Rusak/Cacat',
            'hilang' => 'Hilang',
            'lainnya' => 'Lainnya',
            default => $this->alasan,
        };
    }
}