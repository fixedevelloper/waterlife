<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CollectResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,

            'collector' => [
                'id' => $this->collector?->id,
                'name' => $this->collector?->name,
                'phone' => $this->collector?->phone,
            ],

            'status' => $this->status,
            'collected_at' => $this->collected_at,

            'collection_image' => $this->collection_image
        ? asset('storage/'.$this->collection_image)
        : null,

            'items' => CollectItemResource::collection(
        $this->whenLoaded('items')
    ),

            'created_at' => $this->created_at,
        ];
    }
}
