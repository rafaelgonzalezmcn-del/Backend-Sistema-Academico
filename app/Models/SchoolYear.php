<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolYear extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'active',
        'grade_order',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'active' => 'boolean',
        'grade_order' => 'integer',
    ];

    /**
     * Scope para ordenar años lectivos por su orden académico.
     * Los años sin grade_order definido van al final, ordenados por start_date.
     */
    public function scopeOrdered($query)
    {
        return $query->orderByRaw('grade_order IS NULL')
                     ->orderBy('grade_order', 'asc')
                     ->orderBy('start_date', 'asc');
    }

    /**
     * Obtener el siguiente año lectivo en la secuencia académica.
     * Usa grade_order como criterio principal, fallback a start_date.
     *
     * @return SchoolYear|null
     */
    public function nextYear(): ?SchoolYear
    {
        $query = self::query();

        if ($this->grade_order !== null) {
            // Criterio principal: grade_order mayor
            $query->where(function ($q) {
                $q->where('grade_order', '>', $this->grade_order)
                  ->orWhereNull('grade_order');
            });
        } else {
            // Fallback: start_date mayor
            $query->where('start_date', '>', $this->start_date);
        }

        return $query->ordered()->first();
    }

    /**
     * Obtener el año lectivo activo
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Relación con grados - ELIMINADA
     * Los grados ya no tienen año lectivo (el año vive en secciones)
     */
    
    /**
     * Relación con secciones
     */
    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    /**
     * Relación con horarios - ELIMINADA
     * Los horarios derivan su año lectivo desde section
     * Para obtener horarios de un año lectivo, usar:
     * ClassSchedule::whereHas('section', fn($q) => $q->where('school_year_id', $yearId))
     */

    /**
     * Verificar si el año lectivo está activo
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Verificar si una fecha está dentro del período del año lectivo
     */
    public function containsDate($date): bool
    {
        if (!$this->start_date || !$this->end_date) {
            return true; // Si no hay fechas definidas, permitir todo
        }

        return $date->between($this->start_date, $this->end_date);
    }
}
