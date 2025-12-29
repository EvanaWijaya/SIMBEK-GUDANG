<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipe',
        'sumber',
        'qty',
        'product_stock_id',
        'material_id',
        'ref_id',
        'notes',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
    ];

    /**
     * Relationships
     */

    // Movement untuk product stock
    public function productStock()
    {
        return $this->belongsTo(ProductStock::class);
    }

    // Movement untuk material
    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Scopes
     */

    // Movement masuk
    public function scopeMasuk($query)
    {
        return $query->where('tipe', 'masuk');
    }

    // Movement keluar
    public function scopeKeluar($query)
    {
        return $query->where('tipe', 'keluar');
    }

    // Filter by sumber
    public function scopeBySumber($query, $sumber)
    {
        return $query->where('sumber', $sumber);
    }

    // Movement untuk material
    public function scopeForMaterial($query, $materialId)
    {
        return $query->where('material_id', $materialId);
    }

    // Movement untuk product
    public function scopeForProduct($query, $productId)
    {
        return $query->whereHas('productStock', function ($q) use ($productId) {
            $q->where('product_id', $productId);
        });
    }

    // Movement hari ini
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    // Movement dalam range tanggal
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // Movement 30 hari terakhir (untuk ROP calculation)
    public function scopeLast30Days($query)
    {
        return $query->where('created_at', '>=', now()->subDays(30));
    }

    /**
     * Methods
     */

    // Check apakah movement masuk
    public function isInbound(): bool
    {
        return $this->tipe === 'masuk';
    }

    // Check apakah movement keluar
    public function isOutbound(): bool
    {
        return $this->tipe === 'keluar';
    }

    // Get reference model (production/sale/disposal)
    public function getReference()
    {
        if (!$this->ref_id) {
            return null;
        }

        return match($this->sumber) {
            'production' => Production::find($this->ref_id),
            'sale' => Sale::find($this->ref_id),
            'disposal' => StockDisposal::find($this->ref_id),
            default => null,
        };
    }
}