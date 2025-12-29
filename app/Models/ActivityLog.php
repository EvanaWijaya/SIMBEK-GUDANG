<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'aksi',
        'catatan',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Relationships
     */

    // Log milik user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */

    // Filter by aksi
    public function scopeByAksi($query, $aksi)
    {
        return $query->where('aksi', $aksi);
    }

    // Log login
    public function scopeLogin($query)
    {
        return $query->where('aksi', 'login');
    }

    // Log logout
    public function scopeLogout($query)
    {
        return $query->where('aksi', 'logout');
    }

    // Log produksi
    public function scopeProduksi($query)
    {
        return $query->where('aksi', 'produksi');
    }

    // Log penjualan
    public function scopePenjualan($query)
    {
        return $query->where('aksi', 'penjualan');
    }

    // Log disposal
    public function scopeDisposal($query)
    {
        return $query->where('aksi', 'disposal');
    }

    // Log hari ini
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    // Log bulan ini
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                     ->whereYear('created_at', now()->year);
    }

    // Log dalam range tanggal
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // Log untuk user tertentu
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Accessors
     */

    // Get aksi label yang lebih readable
    public function getAksiLabelAttribute(): string
    {
        return match($this->aksi) {
            'login' => 'Login ke Sistem',
            'logout' => 'Logout dari Sistem',
            'produksi' => 'Melakukan Produksi',
            'penjualan' => 'Melakukan Penjualan',
            'disposal' => 'Melakukan Disposal',
            'create_material' => 'Tambah Bahan Baku',
            'update_material' => 'Update Bahan Baku',
            'delete_material' => 'Hapus Bahan Baku',
            'create_product' => 'Tambah Produk',
            'update_product' => 'Update Produk',
            'delete_product' => 'Hapus Produk',
            'create_formula' => 'Buat Formula',
            'update_formula' => 'Update Formula',
            'delete_formula' => 'Hapus Formula',
            default => $this->aksi,
        };
    }

    // Parse catatan JSON
    public function getParsedCatatanAttribute()
    {
        $decoded = json_decode($this->catatan, true);
        return is_array($decoded) ? $decoded : $this->catatan;
    }
}