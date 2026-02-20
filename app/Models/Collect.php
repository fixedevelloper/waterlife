<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Collect extends Model
{
    use HasFactory;

    protected $fillable = ['order_id','collector_id','collected_at','status','collection_image'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function collector()
    {
        return $this->belongsTo(Agent::class,'collector_id');
    }

    public function items()
    {
        return $this->hasMany(CollectItem::class);
    }
}
