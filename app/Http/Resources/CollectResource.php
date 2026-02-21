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
            'location'=>[
                'latitude'=>$this->order->address->latitude,
                'longitude'=>$this->order->address->longitude
            ],

            // 🔹 Collector
            'collector' => $this->whenLoaded('collector', function () {
                $user = $this->collector?->user;
            return $user ? [
                'id' => $this->collector->id,
                'name' => $user->name,
                'phone' => $user->phone,
            ] : null;
        }),

            // 🔹 Customer
            'customer' => $this->whenLoaded('order', function () {
                $customer = $this->order?->customer;
            $user = $customer?->user;
            return $user ? [
                'id' => $customer->id,
                'name' => $user->name,
                'phone' => $user->phone,
            ] : null;
        }),

            'status' => $this->status,
            'collected_at' => optional($this->collected_at)?->toDateTimeString(),

        // 🔹 Collection image URL
        'collection_image' => $this->collection_image
        ? url('storage/' . $this->collection_image)
        : null,

        // 🔹 Items
        'items' => CollectItemResource::collection(
        $this->whenLoaded('items')
    ),

        'created_at' => $this->created_at?->toDateTimeString(),
    ];
}
}
