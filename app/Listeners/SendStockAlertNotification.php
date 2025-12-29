<?php

namespace App\Listeners;

use App\Events\StockBelowROP;
use App\Models\Material;
use App\Models\Product;
use App\Notifications\StockAlertNotification;
use Illuminate\Support\Facades\Notification;

class SendStockAlertNotification
{
    public function handle(StockBelowROP $event): void
    {
        $entity = match ($event->entityType) {
            'material' => Material::find($event->entityId),
            'product'  => Product::find($event->entityId),
            default    => null,
        };

        if (!$entity) {
            return;
        }

        Notification::send(
            auth()->user(),
            new StockAlertNotification(
                $event->entityType,
                $entity,
                $event->currentStock,
                $event->rop
            )
        );
    }
}
