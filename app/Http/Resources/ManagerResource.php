<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManagerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'forage_name' => $this->forage_name,
            'balance' => $this->balance,
            'is_active' => $this->is_active,
                'name' => $this->user?->name,
                'phone' => $this->user?->phone,
        'email' => $this->user?->email,

            'zone' => $this->zone?->name,

            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
