<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormulaDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'formula_id',
        'material_id',
        'qty',
        'satuan',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
    ];

    /**
     * Relationships
     */

    // Detail milik formula
    public function formula()
    {
        return $this->belongsTo(Formula::class);
    }

    // Material yang dipakai
    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Methods
     */

    // Hitung kebutuhan untuk qty produksi tertentu
    public function calculateNeed($productionQty): float
    {
        return $this->qty * $productionQty;
    }

    // Check apakah stok material cukup
    public function isMaterialSufficient($productionQty): bool
    {
        $needed = $this->calculateNeed($productionQty);
        return $this->material->stok >= $needed;
    }

    // Get total cost untuk detail ini
    public function getTotalCost($productionQty): float
    {
        return $this->calculateNeed($productionQty) * $this->material->harga;
    }
}