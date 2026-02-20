<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ZoneResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'delivery_fee' => $this->delivery_fee,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}

