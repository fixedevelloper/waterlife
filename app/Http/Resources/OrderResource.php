<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,

            // -----------------------------
            // CUSTOMER
            // -----------------------------
            'customer' => new CustomerResource(
                $this->whenLoaded('customer')
            ),

            // -----------------------------
            // AGENTS
            // -----------------------------
            'collector' => new AgentResource(
                $this->whenLoaded('collector')
            ),

            'delivery_agent' => new AgentResource(
                $this->whenLoaded('deliveryAgent')
            ),

            // -----------------------------
            // ADDRESS
            // -----------------------------
            'address' => $this->whenLoaded('address', function () {
                return [
                    'id' => $this->address->id,
                    'full_address' => $this->address->full_address,
                    'label' => $this->address->label,
                    'latitude' => $this->address->latitude,
                    'longitude' => $this->address->longitude,
                ];
            }),

            // -----------------------------
            // ZONE
            // -----------------------------
            'zone' => new ZoneResource(
                $this->whenLoaded('zone')
            ),
            'payment' => new PaymentResource(
                $this->whenLoaded('payment')
            ),
            // -----------------------------
            // FINANCIALS
            // -----------------------------
            'subtotal' => (float) $this->subtotal,
            'delivery_fee' => (float) $this->delivery_fee,
            'total_amount' => (float) $this->total_amount,
            'commission_amount' => (float) $this->commission_amount,
            'platform_margin' => (float) $this->platform_margin,

            // -----------------------------
            // STATUS
            // -----------------------------
            'collection_status' => $this->collection_status,
            'delivery_status' => $this->delivery_status,
            'status' => $this->status,

            // Helper flags pour mobile UX
            'is_pending' => $this->status === 'pending',
            'is_processing' => $this->status === 'processing',
            'is_delivered' => $this->status === 'delivered',

            // -----------------------------
            // DATES
            // -----------------------------
            'scheduled_at' => $this->scheduled_at,
            'delivered_at' => $this->delivered_at,
            'created_at' => $this->created_at,

            // -----------------------------
            // ITEMS
            // -----------------------------
            'items' => OrderItemResource::collection(
                $this->whenLoaded('items')
            ),
            'total_quantity'=>$this->total_quantity
        ];
    }
}
