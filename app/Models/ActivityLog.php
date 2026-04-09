<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'description',
        'subject_type',
        'subject_id',
        'user_id',
        'changes',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    // Relación con el usuario que realizó la acción
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relación con el modelo afectado (polimórfica)
    public function subject()
    {
        return $this->morphTo();
    }
}
