<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'phone', 'password', 'role', 'is_active', 'last_login_at','name'
    ];

    protected $hidden = [
        'password',
    ];

    public function customer()
    {
        return $this->hasOne(Customer::class);
    }

    public function agent()
    {
        return $this->hasOne(Agent::class);
    }
}
