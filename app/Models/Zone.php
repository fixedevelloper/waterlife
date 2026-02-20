<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = ['name','delivery_fee','is_active'];

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function agents()
    {
        return $this->hasMany(Agent::class);
    }
}
