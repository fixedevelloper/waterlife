<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,
            'order_number' => $this->order_number,

            // -----------------------------
            // ADDRESS
            // -----------------------------
            'address' => $this->whenLoaded('address', function () {
                return [
                    'id' => $this->address->id,
                    'full_address' => $this->address->label,
                    'latitude' => $this->address->latitude,
                    'longitude' => $this->address->longitude,
                ];
            }),

            /*
            |--------------------------------------------------------------------------
            | TRACKING SYSTEM
            |--------------------------------------------------------------------------
            */

            'tracking' => [
                'current_step' => $this->getCurrentStep(),
                'progress_percentage' => $this->getProgressPercentage(),
                'status_label' => $this->getStatusLabel(),
                'status_color' => $this->getStatusColor(),
                'timeline' => $this->getTimeline(),
            ],

            /*
            |--------------------------------------------------------------------------
            | BASIC STATUS
            |--------------------------------------------------------------------------
            */

            'status' => $this->status,
            'collection_status' => $this->collection_status,
            'delivery_status' => $this->delivery_status,

            /*
            |--------------------------------------------------------------------------
            | FINANCIAL
            |--------------------------------------------------------------------------
            */

            'total_amount' => (float) $this->total_amount,
            'delivery_fee' => (float) $this->delivery_fee,

            /*
            |--------------------------------------------------------------------------
            | RELATIONS
            |--------------------------------------------------------------------------
            */

            'customer' => new CustomerResource(
                $this->whenLoaded('customer')
            ),
            'collect' => new CollectResource($this->whenLoaded('collect')),
            'delivery' => new DeliveryResource($this->whenLoaded('delivery')),


            'items' => OrderItemResource::collection(
                $this->whenLoaded('items')
            ),

            'created_at' => $this->created_at,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | TRACKING LOGIC
    |--------------------------------------------------------------------------
    */

    private function getCurrentStep(): int
    {
        return match ($this->status) {
        'pending' => 1,
            'collector_assigned' => 2,
            'processing' => 3,
            'delivery_assigned' => 4,
            'delivered' => 5,
            default => 0,
        };
    }

    private function getProgressPercentage(): int
    {
        return match ($this->status) {
        'pending' => 10,
            'collector_assigned' => 30,
            'processing' => 55,
            'delivery_assigned' => 80,
            'delivered' => 100,
            default => 0,
        };
    }

    private function getStatusLabel(): string
    {
        return match ($this->status) {
        'pending' => 'Commande en attente',
            'collector_assigned' => 'Collecteur assigné',
            'processing' => 'En cours de traitement',
            'delivery_assigned' => 'En livraison',
            'delivered' => 'Livré',
            'cancelled' => 'Annulée',
            default => 'Inconnu',
        };
    }

    private function getStatusColor(): string
    {
        return match ($this->status) {
        'pending' => '#FFA500',
            'collector_assigned' => '#2196F3',
            'processing' => '#3F51B5',
            'delivery_assigned' => '#9C27B0',
            'delivered' => '#4CAF50',
            'cancelled' => '#F44336',
            default => '#9E9E9E',
        };
    }

    private function getTimeline(): array
    {
        return [
            [
                'step' => 1,
                'label' => 'Commande créée',
                'completed' => true,
            ],
            [
                'step' => 2,
                'label' => 'Collecte assignée',
                'completed' => in_array($this->status, [
                    'collector_assigned',
                    'processing',
                    'delivery_assigned',
                    'delivered'
                ]),
            ],
            [
                'step' => 3,
                'label' => 'Bidons collectés',
                'completed' => in_array($this->status, [
                    'processing',
                    'delivery_assigned',
                    'delivered'
                ]),
            ],
            [
                'step' => 4,
                'label' => 'En livraison',
                'completed' => in_array($this->status, [
                    'delivery_assigned',
                    'delivered'
                ]),
            ],
            [
                'step' => 5,
                'label' => 'Livré',
                'completed' => $this->status === 'delivered',
            ],
        ];
    }
}
