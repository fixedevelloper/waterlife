<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number','customer_id','collector_id','delivery_agent_id','address_id','zone_id',
        'subtotal','delivery_fee','total_amount',
        'collection_status','delivery_status','status',
        'scheduled_at','delivered_at','commission_amount','platform_margin'
    ];
    protected $appends = ['total_quantity'];

    public function getTotalQuantityAttribute()
    {
        return $this->items->sum('quantity');
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function collector()
    {
        return $this->belongsTo(Agent::class,'collector_id');
    }

    public function deliveryAgent()
    {
        return $this->belongsTo(Agent::class,'delivery_agent_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function collects()
    {
        return $this->hasMany(Collect::class);
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function containerTransactions()
    {
        return $this->hasMany(ContainerTransaction::class);
    }
}
