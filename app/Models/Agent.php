<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','zone_id','can_collect','can_deliver','is_available','vehicle_type','rating_avg','rating_count'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function collectedOrders()
    {
        return $this->hasMany(Order::class, 'collector_id');
    }

    public function deliveredOrders()
    {
        return $this->hasMany(Order::class, 'delivery_agent_id');
    }

    public function collects()
    {
        return $this->hasMany(Collect::class, 'collector_id');
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'delivery_agent_id');
    }
}
