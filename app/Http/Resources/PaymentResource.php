<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'method' => $this->method,
            'transaction_reference' => $this-transaction_reference,
            'amount' => $this->amount,
            'status' => $this->status,
            'paid_at' => $this->paid_at
        ];
    }
}
