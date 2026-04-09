<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name'
    ];

    /**
     * Obtener los profesores que dictan esta materia
     */
    public function profesors()
    {
        return $this->hasManyThrough(
            User::class,
            ClassSchedule::class,
            'subject_id', // Foreign key on ClassSchedule
            'id',         // Foreign key on User
            'id',         // Local key on Subject
            'teacher_id'  // Local key on ClassSchedule
        )->distinct();
    }

    /**
     * Obtener los estudiantes de las secciones que tienen esta materia
     */
    public function estudiantes()
    {
        // Obtener secciones que tienen esta materia asignada
        $sectionIds = ClassSchedule::where('subject_id', $this->id)
            ->pluck('section_id')
            ->unique();

        // Obtener usuarios de esas secciones con rol estudiante
        return User::whereIn('section_id', $sectionIds)
            ->whereHas('role', function ($query) {
                $query->where('name', 'estudiante');
            })->distinct();
    }

    /**
     * Obtener todos los participantes (profesores + estudiantes)
     */
    public function getParticipantes()
    {
        $participantes = [];

        // Profesores
        $profesores = $this->profesors()->with('role')->get();
        foreach ($profesores as $profesor) {
            $participantes[] = [
                'id' => $profesor->id,
                'nombre' => $profesor->first_name . ' ' . $profesor->last_name,
                'correo' => $profesor->email,
                'rol' => 'profesor'
            ];
        }

        // Estudiantes
        $estudiantes = $this->estudiantes()->with('role')->get();
        foreach ($estudiantes as $estudiante) {
            $participantes[] = [
                'id' => $estudiante->id,
                'nombre' => $estudiante->first_name . ' ' . $estudiante->last_name,
                'correo' => $estudiante->email,
                'rol' => 'estudiante'
            ];
        }

        return $participantes;
    }

    // ============================================
    // RELACIONES PARA HISTORIAL ACADÉMICO
    // ============================================

    /**
     * Inscripciones de estudiantes a esta materia
     */
    public function enrollments()
    {
        return $this->hasMany(StudentCourse::class, 'subject_id');
    }

    /**
     * Estudiantes inscritos (vía enrollments)
     */
    public function enrolledStudents()
    {
        return $this->hasManyThrough(
            User::class,
            StudentCourse::class,
            'subject_id',
            'student_id',
            'id',
            'id'
        );
    }
}
