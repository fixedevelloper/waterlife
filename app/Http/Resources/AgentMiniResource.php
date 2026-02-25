<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentMiniResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->user?->name,
             'email' => $this->user?->email,
             'phone' => $this->user?->phone,
            'can_collect' => $this->can_collect,
            'can_deliver' => $this->can_deliver,
            'is_available' => $this->is_available,

            'vehicle_type' => $this->vehicle_type,
        ];
    }
}
