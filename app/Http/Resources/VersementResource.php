<?php


namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VersementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'method' => $this->method,
            'reference' => $this->reference,
            'provider' => $this->provider,
            'status' => $this->status,

            'manager' => [
                'id' => $this->manager?->id,
                'forage_name' => $this->manager?->forage_name,
            ],

            'validated_by' => $this->validator?->name,
            'validated_at' => $this->validated_at,

            'period' => [
        'start' => $this->period_start,
        'end' => $this->period_end,
    ],

            'note' => $this->note,
            'created_at' => $this->created_at,
        ];
    }
}
