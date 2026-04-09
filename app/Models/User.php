<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'identification_number',
        'phone',
        'role_id',
        'section_id',
        'activo',
        'last_login_at',
        // Selfie (foto de perfil en BD)
        'selfie',
        'selfie_mime'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        // Ocultar datos binarios en respuestas JSON
        'selfie',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'activo' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    /**
     * Relación con rol
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Relación con sección (para estudiantes)
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Horarios donde es profesor
     */
    public function taughtSchedules()
    {
        return $this->hasMany(ClassSchedule::class, 'teacher_id');
    }

    /**
     * Verificar si tiene un rol específico
     */
    public function hasRole($role)
    {
        return $this->role && $this->role->name === $role;
    }

    /**
     * Verificar si es administrador
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Verificar si es profesor
     */
    public function isTeacher(): bool
    {
        return $this->hasRole('profesor');
    }

    /**
     * Verificar si es estudiante
     */
    public function isStudent(): bool
    {
        return $this->hasRole('estudiante');
    }

    /**
     * Obtener nombre completo
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    // ============================================
    // RELACIONES PARA HISTORIAL ACADÉMICO
    // ============================================

    /**
     * Cursos donde es estudiante
     */
    public function studentCourses()
    {
        return $this->hasMany(StudentCourse::class, 'student_id');
    }

    /**
     * Cursos donde es profesor
     */
    public function professorCourses()
    {
        return $this->hasMany(StudentCourse::class, 'profesor_id');
    }
}
