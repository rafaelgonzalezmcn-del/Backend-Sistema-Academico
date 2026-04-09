<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grade extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'grade_order',
    ];

    protected $casts = [
        'grade_order' => 'integer',
    ];

    /**
     * Scope para ordenar grados por su orden secuencial.
     * Los grados sin grade_order definido van al final.
     */
    public function scopeOrdered($query)
    {
        return $query->orderByRaw('grade_order IS NULL')
                     ->orderBy('grade_order', 'asc')
                     ->orderBy('name', 'asc');
    }

    /**
     * Obtener el siguiente grado en la secuencia.
     * Retorna null si este es el último grado.
     */
    public function nextGrade(): ?Grade
    {
        if ($this->grade_order === null) return null;

        return Grade::where('grade_order', '>', $this->grade_order)
            ->ordered()
            ->first();
    }

    /**
     * Obtener el grado anterior en la secuencia.
     * Retorna null si este es el primer grado.
     */
    public function previousGrade(): ?Grade
    {
        if ($this->grade_order === null) return null;

        return Grade::where('grade_order', '<', $this->grade_order)
            ->ordered()
            ->orderByDesc('grade_order')
            ->first();
    }

    /**
     * Relación con secciones
     * Un grado (Primero, Segundo, etc.) tiene muchas secciones
     * Las secciones definen el año lectivo
     */
    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    /**
     * Obtener estudiantes del grado
     */
    public function students()
    {
        return $this->hasManyThrough(
            User::class,
            Section::class,
            'grade_id',
            'section_id'
        // F3-T1: Estandarizado a 'estudiante' (antes 'student')
        )->whereHas('role', fn($q) => $q->where('name', 'estudiante'));
    }
}
