<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class DeliveryItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product' => $this->whenLoaded('product', function () {
                return [
                    'name' => $this->product?->name,
                    'volume_liters' => $this->product?->volume_liters,
                ];
            }),
            'quantity_delivered' => $this->quantity_delivered,
            'quantity_collected' => $this->quantity_collected,
        ];
    }
}
