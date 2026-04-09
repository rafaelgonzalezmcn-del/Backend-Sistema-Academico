<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parcial extends Model
{
    use HasFactory;

    protected $table = 'parciales';

    protected $fillable = [
        'nombre',
        'numero',
        'modulo_id',
        'nota_maxima',
        'fecha_inicio',
        'fecha_fin'
    ];

    protected $casts = [
        'nota_maxima' => 'integer',
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime'
    ];

    /**
     * Relación con módulo
     */
    public function modulo()
    {
        return $this->belongsTo(Modulo::class);
    }

    /**
     * Relación con parámetros
     */
    public function parametros()
    {
        return $this->hasMany(Parametro::class);
    }

    /**
     * Relación con tareas
     */
    public function tareas()
    {
        return $this->hasMany(Tarea::class);
    }
}
