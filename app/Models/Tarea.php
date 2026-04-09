<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tarea extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'titulo',
        'descripcion',
        'fecha_limite',
        'modulo_id',
        'archivo_ruta',
        'archivo_nombre',
        'archivo_tamano',
        'puntaje_maximo',
        'parcial_id',
        'parametro_id',
        'deleted_at'
    ];

    protected $casts = [
        'fecha_limite' => 'datetime'
    ];

    /**
     * Relación con el módulo
     */
    public function modulo()
    {
        return $this->belongsTo(Modulo::class);
    }

    /**
     * Relación con parcial
     */
    public function parcial()
    {
        return $this->belongsTo(Parcial::class);
    }

    /**
     * Relación con parámetro
     */
    public function parametro()
    {
        return $this->belongsTo(Parametro::class);
    }

    /**
     * Relación con entregas
     */
    public function entregas()
    {
        return $this->hasMany(Entrega::class);
    }

    /**
     * Obtener el estado de la tarea
     */
    public function getEstado()
    {
        if (!$this->fecha_limite) {
            return 'pendiente';
        }

        return now()->greaterThan($this->fecha_limite) ? 'vencida' : 'pendiente';
    }

    /**
     * Verificar si está vencida
     */
    public function isVencida()
    {
        return $this->getEstado() === 'vencida';
    }

    /**
     * Verificar si está pendiente
     */
    public function isPendiente()
    {
        return $this->getEstado() === 'pendiente';
    }
}
