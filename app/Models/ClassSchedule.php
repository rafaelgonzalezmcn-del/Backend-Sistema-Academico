<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'teacher_id',
        'subject_id',
        'section_id',
        // school_year_id fue eliminado - se deriva automáticamente desde section
        'day',
        'start_time',
        'end_time'
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    /**
     * Relación con profesor
     */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Relación con materia
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Relación con sección
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Relación con año lectivo (deriva desde section)
     * Nota: La columna school_year_id fue eliminada de class_schedules
     * y ahora se deriva desde section.school_year_id
     */
    public function schoolYear()
    {
        return $this->hasOneThrough(
            SchoolYear::class,
            Section::class,
            'id',           // Foreign key on Section (local key on this model)
            'id',           // Foreign key on SchoolYear (local key on Section)
            'section_id',   // Local key on this model (ClassSchedule)
            'school_year_id' // Local key on Section
        );
    }

    /**
     * Scope para filtrar por año lectivo
     * AHORA: Deriva desde section en lugar de columna propia
     */
    public function scopeForSchoolYear($query, $schoolYearId)
    {
        return $query->whereHas('section', function ($q) use ($schoolYearId) {
            $q->where('school_year_id', $schoolYearId);
        });
    }

    /**
     * Scope para filtrar por día
     */
    public function scopeForDay($query, $day)
    {
        return $query->where('day', $day);
    }

    /**
     * Verificar si hay conflicto de horario con otro schedule.
     * Dos horarios se superponen si:
     *  - Son el mismo día
     *  - El inicio de uno es antes del fin del otro Y el fin de uno es después del inicio del otro
     *
     * Maneja tanto strings ('08:00') como Carbon instances.
     */
    public function overlapsWith(ClassSchedule $other): bool
    {
        if ($this->day !== $other->day) {
            return false;
        }

        // Normalizar a strings para comparación consistente
        $thisStart = $this->formatTime($this->start_time);
        $thisEnd = $this->formatTime($this->end_time);
        $otherStart = $this->formatTime($other->start_time);
        $otherEnd = $this->formatTime($other->end_time);

        return $thisStart < $otherEnd && $thisEnd > $otherStart;
    }

    /**
     * Formatea un valor de tiempo a string 'HH:MM' para comparación.
     * Acepta Carbon instances, strings o null.
     */
    private function formatTime($time): string
    {
        if ($time === null) {
            return '00:00';
        }

        if ($time instanceof \Carbon\Carbon) {
            return $time->format('H:i');
        }

        // Normalizar strings: "08:00:00" → "08:00", "8:00" → "08:00"
        $timeStr = (string) $time;
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $timeStr, $matches)) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return $timeStr;
    }

    /**
     * Obtener duración de la clase en minutos
     */
    public function getDurationInMinutesAttribute(): int
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);

        return $start->diffInMinutes($end);
    }
}
