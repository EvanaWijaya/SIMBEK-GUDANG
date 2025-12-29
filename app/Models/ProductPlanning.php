<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPlanning extends Model
{
    protected $table = 'product_planning';

    protected $fillable = [
        'product_id',
        'stok_min',
        'lead_time_days',
        'safety_stock',
    ];

    /**
     * Relasi ke Product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
