<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AgentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,

            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'phone' => $this->user?->phone,
            ],

            'zone' => new ZoneResource(
        $this->whenLoaded('zone')
    ),

            'can_collect' => $this->can_collect,
            'can_deliver' => $this->can_deliver,
            'is_available' => $this->is_available,

            'vehicle_type' => $this->vehicle_type,

            'rating_avg' => $this->rating_avg,
            'rating_count' => $this->rating_count,

            'created_at' => $this->created_at,
        ];
    }
}
