<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;


class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'kategori',
        'nama_material',
        'satuan',
        'stok',
        'stok_min',
        'lead_time_days',
        'safety_stock',
        'harga',
        'supplier',
        'expired_date',
    ];

    protected $casts = [
        'stok' => 'decimal:2',
        'stok_min' => 'decimal:2',
        'safety_stock' => 'decimal:2',
        'harga' => 'decimal:2',
        'lead_time_days' => 'integer',
        'expired_date' => 'date',
    ];

    /**
     * Relationships
     */

    // Material dipakai di formula details
    public function formulaDetails()
    {
        return $this->hasMany(FormulaDetail::class);
    }

    // Stock movements untuk material ini
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Scopes
     */

    // Material yang stoknya di bawah minimum
    public function scopeLowStock($query)
    {
        return $query->whereColumn('stok', '<=', 'stok_min');
    }

    // Material yang perlu reorder (stok <= ROP)
    public function scopeNeedReorder($query)
    {
        return $query->where(function($q) {
            $q->whereColumn('stok', '<=', 'safety_stock')
              ->orWhereRaw('stok <= (lead_time_days * (
                  SELECT COALESCE(SUM(qty) / 30, 0)
                  FROM stock_movements
                  WHERE material_id = materials.id
                    AND tipe = "keluar"
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              ) + safety_stock)');
        });
    }

    // Material yang hampir expired (30 hari)
    public function scopeNearExpiry($query, $days = 30)
    {
        return $query->whereNotNull('expired_date')
                     ->whereDate('expired_date', '<=', now()->addDays($days));
    }

    // Filter by kategori
    public function scopeByKategori($query, $kategori)
    {
        return $query->where('kategori', $kategori);
    }

    /**
     * Accessors
     */

    // Check apakah stok rendah
    public function isLowStock(): bool
    {
        return $this->stok <= $this->stok_min;
    }

    // Check apakah hampir expired
    public function isNearExpiry($days = 30): bool
    {
        if (!$this->expired_date) {
            return false;
        }
        return Carbon::parse($this->expired_at)->lte(now());
    }

    // Get status stok (aman/rendah/habis)
    public function getStockStatusAttribute(): string
    {
        if ($this->stok <= 0) {
            return 'habis';
        } elseif ($this->stok <= $this->stok_min) {
            return 'rendah';
        }
        return 'aman';
    }

    // Format harga dengan currency
    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format((float) ($this->harga ?? 0), 2);
    }
}