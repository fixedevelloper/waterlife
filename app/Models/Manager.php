<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manager extends Model
{
    protected $fillable = [
        'user_id',
        'zone_id',
        'forage_name',
        'balance',
        'is_active'
    ];

    protected $casts = [
        'balance' => 'float',
        'is_active' => 'boolean'
    ];

    // 🔗 Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function versements()
    {
        return $this->hasMany(Versement::class);
    }
}
