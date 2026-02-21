<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
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
            'delivery_agent' => [
                'id' => $this->deliveryAgent?->id,
                'name' => $this->deliveryAgent?->agent->name,
                'phone' => $this->deliveryAgent?->agent->phone,
            ],

            'status' => $this->status,

            'assigned_at' => $this->assigned_at,
            'picked_at' => $this->picked_at,
            'delivered_at' => $this->delivered_at,

            'delivery_proof_type' => $this->delivery_proof_type,
            'delivery_proof_value' => $this->delivery_proof_value,
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
            'delivery_image' => $this->delivery_image
        ? asset('storage/'.$this->delivery_image)
        : null,

            'items' => DeliveryItemResource::collection(
        $this->whenLoaded('items')
    ),

            'created_at' => $this->created_at,
        ];
    }
}
