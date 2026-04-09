<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Entrega extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tarea_id',
        'estudiante_id',
        'archivo',
        'fecha_entrega',
        'nota',
        'observaciones',
        'deleted_at'
    ];

    protected $casts = [
        'fecha_entrega' => 'datetime',
        'nota' => 'decimal:2'
    ];

    /**
     * Relación con la tarea
     */
    public function tarea()
    {
        return $this->belongsTo(Tarea::class);
    }

    /**
     * Relación con el estudiante
     */
    public function estudiante()
    {
        return $this->belongsTo(User::class);
    }
}
