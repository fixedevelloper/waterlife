<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Versement extends Model
{
    protected $fillable = [
        'manager_id',
        'amount',
        'method',
        'reference',
        'provider',
        'status',
        'validated_by',
        'validated_at',
        'period_start',
        'period_end',
        'note'
    ];

    protected $casts = [
        'amount' => 'float',
        'validated_at' => 'datetime',
        'period_start' => 'date',
        'period_end' => 'date'
    ];

    // 🔗 Relations
    public function manager()
    {
        return $this->belongsTo(Manager::class);
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
