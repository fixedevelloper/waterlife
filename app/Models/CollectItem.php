<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CollectItem extends Model
{
    use HasFactory;

    protected $fillable = ['collect_id','product_id','quantity_collected'];

    public function collect()
    {
        return $this->belongsTo(Collect::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
