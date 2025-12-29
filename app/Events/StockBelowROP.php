<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockBelowROP
{
    use Dispatchable, SerializesModels;

    public string $entityType; // material | product
    public int $entityId;
    public float $currentStock;
    public float $rop;

    public function __construct(
        string $entityType,
        int $entityId,
        float $currentStock,
        float $rop
    ) {
        $this->entityType    = $entityType;
        $this->entityId      = $entityId;
        $this->currentStock = $currentStock;
        $this->rop          = $rop;
    }
}
