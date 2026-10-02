<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\ClassSchedule;
use App\Models\Section;
use App\Models\SchoolYear;
use App\Models\User;
use App\Traits\LogsActivity;

class SubjectService
{
    use LogsActivity;
    /**
     * Obtener materias del profesor autenticado
     * Devuelve cursos independientes (materia + sección)
     */
    public function getSubjectsForTeacher(User $teacher): array
    {
        $activeYear = SchoolYear::where('active', true)->first();
        
        $query = ClassSchedule::with([
            'subject:id,name',
            'section:id,name,grade_id,school_year_id',
            'section.grade:id,name'
        ])
        ->where('teacher_id', $teacher->id);

        // school_year_id se deriva desde section (columna eliminada de class_schedules)
        if ($activeYear) {
            $query->whereHas('section', function ($q) use ($activeYear) {
                $q->where('school_year_id', $activeYear->id);
            });
        }

        $schedules = $query->get();

        // Devolver cursos independientes (NO agrupados por materia)
        $courses = [];
        
        foreach ($schedules as $schedule) {
            // Usar subject_id + section_id como key única
            $key = $schedule->subject->id . '-' . $schedule->section->id;
            
            $courses[] = [
                'subject_id' => $schedule->subject->id,
                'subject_name' => $schedule->subject->name,
                'section_id' => $schedule->section->id,
                'section_name' => $schedule->section->name,
                'grade' => $schedule->section->grade->name ?? null
            ];
        }

        // Eliminar duplicados por si acaso
        $courses = array_values(array_unique($courses, SORT_REGULAR));

        return [
            'data' => $courses,
            'meta' => [
                'school_year' => $activeYear?->name,
                'school_year_id' => $activeYear?->id
            ]
        ];
    }

    /**
     * Obtener materias del estudiante autenticado
     */
    public function getSubjectsForStudent(User $student): array
    {
        $activeYear = SchoolYear::where('active', true)->first();
        
        // Obtener la sección del estudiante
        $section = null;
        $sectionName = null;
        $gradeName = null;
        
        if ($student->section_id) {
            $section = Section::with('grade')->find($student->section_id);
            if ($section) {
                $sectionName = $section->name;
                $gradeName = $section->grade?->name;
            }
        }
        
        // Si no tiene sección, no puede ver materias
        if (!$section) {
            return [
                'data' => [],
                'meta' => [
                    'school_year' => $activeYear?->name,
                    'school_year_id' => $activeYear?->id,
                    'section' => null,
                    'message' => 'El estudiante no tiene sección asignada'
                ]
            ];
        }

        $query = ClassSchedule::with([
            'subject:id,name',
            'teacher:id,first_name,last_name'
        ])
        ->where('section_id', $section->id);

        // school_year_id se deriva desde section (columna eliminada de class_schedules)
        if ($activeYear) {
            $query->whereHas('section', function ($q) use ($activeYear) {
                $q->where('school_year_id', $activeYear->id);
            });
        }

        $schedules = $query->get();

        // Agrupar por materia
        $subjectsMap = [];
        
        foreach ($schedules as $schedule) {
            $subjectId = $schedule->subject->id;
            
            if (!isset($subjectsMap[$subjectId])) {
                $subjectsMap[$subjectId] = [
                    'id' => $schedule->subject->id,
                    'name' => $schedule->subject->name,
                    'teacher' => [
                        'id' => $schedule->teacher->id,
                        'name' => $schedule->teacher->first_name . ' ' . $schedule->teacher->last_name
                    ],
                    'section' => [
                        'id' => $section?->id,
                        'name' => $sectionName,
                        'grade' => $gradeName
                    ]
                ];
            }
        }

        return [
            'data' => array_values($subjectsMap),
            'meta' => [
                'school_year' => $activeYear?->name,
                'school_year_id' => $activeYear?->id,
                'section' => $sectionName
            ]
        ];
    }

    /**
     * Obtener participantes de una materia
     * Soporta paginación: ?page=1&per_page=50
     * Sin parámetros: retorna lista simple (backward compatible)
     */
    public function getParticipantes(Subject $subject, User $user): array
    {
        $roleName = $user->role?->name;
        $participantes = [];
        
        // Verificar si se solicita paginación
        $page = request()->query('page');
        $perPage = \App\Support\Paginacion::porPagina(request()->query('per_page'), 50);
        
        if ($roleName === 'profesor') {
            // El profesor solo ve sus estudiantes de la sección específica
            // Recibir sectionId como parámetro opcional para filtrar
            $sectionId = request()->query('section_id');
            
            // Determinar qué secciones usar
            if ($sectionId) {
                // Solo la sección específica seleccionada
                $mySectionIds = [(int)$sectionId];
            } else {
                // Todas las secciones del profesor para esta materia (fallback)
                $mySectionIds = ClassSchedule::where('teacher_id', $user->id)
                    ->where('subject_id', $subject->id)
                    ->pluck('section_id')
                    ->unique()
                    ->toArray();
            }
            
            $query = User::whereIn('section_id', $mySectionIds)
                ->whereHas('role', fn($q) => $q->where('name', 'estudiante'))
                ->where('activo', true);
            
            // Construir participantes para lista simple
            $buildParticipantesList = function ($users) use ($user) {
                $list = [];
                foreach ($users as $estudiante) {
                    $list[] = [
                        'id' => $estudiante->id,
                        'nombre' => $estudiante->first_name . ' ' . $estudiante->last_name,
                        'email' => $estudiante->email,
                        'rol' => 'estudiante',
                        'has_selfie' => !empty($estudiante->selfie)
                    ];
                }
                return $list;
            };
            
            if ($page) {
                // Paginación solicitada
                $estudiantes = $query->paginate($perPage, ['*'], 'page', $page);
                
                foreach ($estudiantes as $estudiante) {
                    $participantes[] = [
                        'id' => $estudiante->id,
                        'nombre' => $estudiante->first_name . ' ' . $estudiante->last_name,
                        'email' => $estudiante->email,
                        'rol' => 'estudiante',
                        'has_selfie' => !empty($estudiante->selfie)
                    ];
                }
                
                // Agregar al profesor mismo
                $participantes[] = [
                    'id' => $user->id,
                    'nombre' => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->email,
                    'rol' => 'profesor',
                    'has_selfie' => !empty($user->selfie)
                ];
                
                return [
                    'data' => $participantes,
                    'meta' => [
                        'current_page' => $estudiantes->currentPage(),
                        'last_page' => $estudiantes->lastPage(),
                        'total' => $estudiantes->total(),
                        'per_page' => $estudiantes->perPage()
                    ]
                ];
            } else {
                // Sin paginación: comportamiento original
                $estudiantes = $query->get();
                $participantes = $buildParticipantesList($estudiantes);
                
                // Agregar al profesor mismo
                $participantes[] = [
                    'id' => $user->id,
                    'nombre' => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->email,
                    'rol' => 'profesor',
                    'has_selfie' => !empty($user->selfie)
                ];
                
                return $participantes;
            }
            
        } elseif ($roleName === 'estudiante') {
            $sectionId = $user->section_id;
            
            // Profesores que enseñan esta materia
            $profesores = ClassSchedule::where('subject_id', $subject->id)
                ->where('section_id', $sectionId)
                ->with('teacher')
                ->get()
                ->pluck('teacher')
                ->unique('id');
            
            foreach ($profesores as $profesor) {
                if ($profesor) {
                    $participantes[] = [
                        'id' => $profesor->id,
                        'nombre' => $profesor->first_name . ' ' . $profesor->last_name,
                        'email' => $profesor->email,
                        'rol' => 'profesor',
                        'has_selfie' => !empty($profesor->selfie)
                    ];
                }
            }
            
            // Compañeros de sección
            $companeros = User::where('section_id', $sectionId)
                ->whereHas('role', fn($q) => $q->where('name', 'estudiante'))
                ->where('id', '!=', $user->id)
                ->where('activo', true)
                ->get();
            
            foreach ($companeros as $comp) {
                $participantes[] = [
                    'id' => $comp->id,
                    'nombre' => $comp->first_name . ' ' . $comp->last_name,
                    'email' => $comp->email,
                    'rol' => 'estudiante',
                    'has_selfie' => !empty($comp->selfie)
                ];
            }

            // Agregar al propio estudiante
            $participantes[] = [
                'id' => $user->id,
                'nombre' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'rol' => 'estudiante',
                'has_selfie' => !empty($user->selfie)
            ];
            
        } else {
            // Admin puede ver todos
            $participantes = $subject->getParticipantes();
        }

        return [
            'data' => $participantes,
            'meta' => [
                'total' => count($participantes),
                'profesores' => count(array_filter($participantes, fn($p) => $p['rol'] === 'profesor')),
                'estudiantes' => count(array_filter($participantes, fn($p) => $p['rol'] === 'estudiante'))
            ]
        ];
    }

    /**
     * Obtener datos de materia para estudiante
     */
    public function getSubjectForStudent(Subject $subject, User $student): array
    {
        $response = [
            'id' => $subject->id,
            'name' => $subject->name,
            'created_at' => $subject->created_at,
            'updated_at' => $subject->updated_at
        ];
        
        // Información de la sección
        $section = null;
        
        if ($student->section_id) {
            $section = Section::with('grade')->find($student->section_id);
        }
        
        if (!$section) {
            $activeYear = SchoolYear::where('active', true)->first();
            $schedule = ClassSchedule::where('subject_id', $subject->id)
                ->with('section.grade');
            
            // school_year_id se deriva desde section (columna eliminada de class_schedules)
            if ($activeYear) {
                $schedule->whereHas('section', function ($q) use ($activeYear) {
                    $q->where('school_year_id', $activeYear->id);
                });
            }
            
            $firstSchedule = $schedule->first();
            if ($firstSchedule?->section) {
                $section = $firstSchedule->section;
            }
        }
        
        if ($section) {
            $response['section'] = [
                'name' => $section->name,
                'grade' => $section->grade?->name
            ];
        }
        
        return $response;
    }

    /**
     * Verificar acceso a materia
     */
    public function canAccessSubject(User $user, Subject $subject): bool
    {
        $roleName = $user->role?->name;
        
        if ($roleName === 'admin') {
            return true;
        }
        
        if ($roleName === 'estudiante') {
            return ClassSchedule::where('section_id', $user->section_id)
                ->where('subject_id', $subject->id)
                ->exists();
        }
        
        if ($roleName === 'profesor') {
            return ClassSchedule::where('teacher_id', $user->id)
                ->where('subject_id', $subject->id)
                ->exists();
        }
        
        return false;
    }

    /**
     * Crear materia
     */
    public function create(array $data): Subject
    {
        $subject = Subject::create([
            'name' => $data['name']
        ]);

        $this->logActivity($this->logEvent('materia', 'created'), $subject, null, true);

        return $subject;
    }

    /**
     * Actualizar materia
     */
    public function update(Subject $subject, array $data): Subject
    {
        $oldData = ['name' => $subject->name];
        
        $subject->update([
            'name' => $data['name']
        ]);

        $changes = $this->getChanges($oldData, $data);
        $this->logActivity($this->logEvent('materia', 'updated'), $subject, $changes);

        return $subject;
    }

    /**
     * Eliminar materia
     */
    public function delete(Subject $subject): void
    {
        $subject->delete();

        $this->logActivity($this->logEvent('materia', 'deleted'), $subject, null, true);
    }
}
