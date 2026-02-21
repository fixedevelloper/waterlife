<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CollectItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,

            'product' => $this->whenLoaded('product', function () {
                return [
                    'name' => $this->product?->name,
                    'volume_liters' => $this->product?->volume_liters,
                ];
            }),

            'quantity_collected' => $this->quantity_collected,
        ];
    }
}
