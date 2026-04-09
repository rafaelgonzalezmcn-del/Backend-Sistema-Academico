<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parametro extends Model
{
    use HasFactory;

    protected $table = 'parametros';

    // Tipos de parámetros predefinidos
    const TIPO_ACTIVIDADES_CLASE = 'actividades_clase';
    const TIPO_TAREAS = 'tareas';
    const TIPO_ACTUACION = 'actuacion';
    const TIPO_EXAMENES = 'examenes';

    protected $fillable = [
        'nombre',
        'tipo',
        'parcial_id',
        'porcentaje',
        'nota_maxima_default',
        'activo'
    ];

    protected $casts = [
        'porcentaje' => 'float',
        'nota_maxima_default' => 'integer',
        'activo' => 'boolean'
    ];

    /**
     * Relación con parcial
     */
    public function parcial()
    {
        return $this->belongsTo(Parcial::class);
    }

    /**
     * Relación con tareas
     */
    public function tareas()
    {
        return $this->hasMany(Tarea::class);
    }

    /**
     * Scope para parámetros activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Obtener el nombre formateado del tipo
     */
    public function getTipoNombreAttribute()
    {
        return match($this->tipo) {
            self::TIPO_ACTIVIDADES_CLASE => 'Actividades en clase',
            self::TIPO_TAREAS => 'Tareas',
            self::TIPO_ACTUACION => 'Actuación',
            self::TIPO_EXAMENES => 'Exámenes',
            default => $this->nombre
        };
    }

    /**
     * Obtener tipos disponibles
     */
    public static function getTipos()
    {
        return [
            self::TIPO_ACTIVIDADES_CLASE => 'Actividades en clase',
            self::TIPO_TAREAS => 'Tareas',
            self::TIPO_ACTUACION => 'Actuación',
            self::TIPO_EXAMENES => 'Exámenes'
        ];
    }
}
