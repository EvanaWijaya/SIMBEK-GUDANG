<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StockAlertNotification extends Notification
{
    use Queueable;

    protected string $type;
    protected $entity;
    protected float $stock;
    protected float $rop;

    public function __construct(
        string $type,
        $entity,
        float $stock,
        float $rop
    ) {
        $this->type  = $type;
        $this->entity = $entity;
        $this->stock = $stock;
        $this->rop   = $rop;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'type'           => $this->type,
            'entity_id'      => $this->entity->id,
            'name'           => $this->entity->nama ?? $this->entity->name,
            'current_stock'  => $this->stock,
            'rop'            => $this->rop,
            'message'        => $this->type === 'material'
                ? "Material {$this->entity->nama_material} perlu direstock"
                : "Produk {$this->entity->name} perlu diproduksi",
        ];
    }
}
