<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name','volume_liters','base_price','is_active'];

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function collectItems()
    {
        return $this->hasMany(CollectItem::class);
    }

    public function deliveryItems()
    {
        return $this->hasMany(DeliveryItem::class);
    }
}
