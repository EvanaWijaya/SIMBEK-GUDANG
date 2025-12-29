<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    use HasFactory;

    protected $table = 'product_stock';

    protected $fillable = [
        'product_id',
        'production_id',
        'qty',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
    ];

    /**
     * Relationships
     */

    // Stok untuk produk
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Stok dari produksi mana
    public function production()
    {
        return $this->belongsTo(Production::class);
    }

    // Stock movements
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    // Disposal
    public function disposals()
    {
        return $this->hasMany(StockDisposal::class);
    }

    /**
     * Scopes
     */

    // Stok yang masih tersedia
    public function scopeAvailable($query)
    {
        return $query->where('qty', '>', 0);
    }

    // Stok untuk produk tertentu
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    // Stok dari produksi tertentu
    public function scopeFromProduction($query, $productionId)
    {
        return $query->where('production_id', $productionId);
    }

    /**
     * Methods
     */

    // Check apakah stok tersedia
    public function isAvailable(): bool
    {
        return $this->qty > 0;
    }

    // Get expired date dari produksi
    public function getExpiredDateAttribute()
    {
        return $this->production->expired_date;
    }

    // Check apakah hampir expired
    public function isNearExpiry($days = 30): bool
    {
        if (!$this->expired_date) {
            return false;
        }
        return $this->expired_date->lte(now()->addDays($days));
    }
}