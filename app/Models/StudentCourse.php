<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentCourse extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'subject_id',
        'section_id',
        'school_year_id',
        'profesor_id',
        'final_grade',
        'parcial_grades',
        'total_score_obtained',
        'total_score_possible',
        'passing_percentage',
        'status',
        'observations',
        'closed_at',
        'closed_by_user_id',
    ];

    protected $casts = [
        'final_grade' => 'decimal:2',
        'closed_at' => 'date',
        'parcial_grades' => 'array',
        'total_score_obtained' => 'decimal:2',
        'total_score_possible' => 'decimal:2',
        'passing_percentage' => 'decimal:2',
    ];

    // ============================================
    // RELACIONES
    // ============================================

    /**
     * Estudiante matriculado
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Materia/Curso
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Sección
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Año lectivo
     */
    public function schoolYear()
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id');
    }

    /**
     * Profesor que dicta la materia
     */
    public function profesor()
    {
        return $this->belongsTo(User::class, 'profesor_id');
    }

    /**
     * Usuario que cerró el curso
     */
    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * Solo cursos cerrados
     */
    public function scopeClosed($query)
    {
        return $query->whereIn('status', ['aprobado', 'reprobado', 'concluido', 'retirado']);
    }

    /**
     * Solo cursos activos (cursando)
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'cursando');
    }

    /**
     * Por año lectivo
     */
    public function scopeForSchoolYear($query, $schoolYearId)
    {
        return $query->where('school_year_id', $schoolYearId);
    }

    /**
     * Por estudiante
     */
    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    // ============================================
    // MÉTODOS AUXILIARES
    // ============================================

    /**
     * Verificar si el curso ya está cerrado
     */
    public function isClosed(): bool
    {
        return in_array($this->status, ['aprobado', 'reprobado', 'concluido', 'retirado']);
    }

    /**
     * Verificar si puede cerrarse (solo si está cursando)
     */
    public function canBeClosed(): bool
    {
        return $this->status === 'cursando';
    }

    /**
     * Obtener nombre del estado
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'cursando' => 'Cursando',
            'aprobado' => 'Aprobado',
            'reprobado' => 'Reprobado',
            'concluido' => 'Concluido',
            'retirado' => 'Retirado',
            default => $this->status,
        };
    }

    /**
     * Determinar estado basado en nota
     */
    public static function determineStatus(float $grade, float $passingGrade = 7.0): string
    {
        if ($grade >= $passingGrade) {
            return 'aprobado';
        }
        return 'reprobado';
    }
}
