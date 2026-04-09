<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Section extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'grade_id',
        'school_year_id',
        'name',
        'max_capacity',
    ];

    protected $casts = [
        'max_capacity' => 'integer',
    ];

    protected $appends = [
        'capacity_info',
    ];

    /**
     * Índices únicos - reforzado en BD y aquí
     */
    protected $uniqueRules = [
        'unique' => 'sections,grade_id,school_year_id,name'
    ];

    /**
     * Relación con grado
     */
    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * Relación con año lectivo
     * El año lectivo vive AQUÍ, no en Grade
     */
    public function schoolYear()
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /**
     * Relación con estudiantes
     */
    public function students()
    {
        return $this->hasMany(User::class, 'section_id')
            ->whereHas('role', fn($q) => $q->where('name', 'estudiante'));
    }

    /**
     * Relación con horarios
     */
    public function classSchedules()
    {
        return $this->hasMany(ClassSchedule::class);
    }

    /**
     * Obtener el nombre completo de la sección (Grado + Nombre + Año)
     */
    public function getFullNameAttribute(): string
    {
        $yearName = $this->schoolYear?->name ?? '';
        return "{$this->grade?->name} - {$this->name}" . ($yearName ? " ({$yearName})" : '');
    }

    /**
     * Contar estudiantes activos matriculados en esta sección.
     * Solo cuenta usuarios con rol 'estudiante' y activo=true.
     */
    public function getEnrolledCount(): int
    {
        return $this->hasMany(User::class, 'section_id')
            ->whereHas('role', fn($q) => $q->where('name', 'estudiante'))
            ->where('activo', true)
            ->count();
    }

    /**
     * Verificar si la sección tiene cupo disponible.
     * Retorna true si max_capacity es null (ilimitada) o si hay cupo.
     */
    public function hasAvailableSpace(): bool
    {
        if ($this->max_capacity === null) return true;
        return $this->getEnrolledCount() < $this->max_capacity;
    }

    /**
     * Obtener información de ocupación para la API.
     * Retorna: { enrolled: 28, max_capacity: 35, is_full: false }
     */
    public function getCapacityInfoAttribute(): array
    {
        $enrolled = $this->getEnrolledCount();
        return [
            'enrolled' => $enrolled,
            'max_capacity' => $this->max_capacity,
            'is_full' => $this->max_capacity !== null && $enrolled >= $this->max_capacity,
            'available' => $this->max_capacity === null ? null : max(0, $this->max_capacity - $enrolled),
        ];
    }
}
