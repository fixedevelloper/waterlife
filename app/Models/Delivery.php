<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id','delivery_agent_id','assigned_at','picked_at','delivered_at',
        'status','delivery_proof_type','delivery_proof_value','delivery_image'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class,'delivery_agent_id');
    }

    public function items()
    {
        return $this->hasMany(DeliveryItem::class);
    }
}
