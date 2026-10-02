<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\Section;
use App\Models\User;
use App\Models\StudentCourse;
use App\Http\Requests\StoreSubjectRequest;
use App\Http\Requests\UpdateSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Services\SubjectService;
use App\Services\CourseClosureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SubjectController extends Controller
{
    const CACHE_TTL = 600; // 10 minutos

    public function __construct(
        private SubjectService $subjectService,
        private CourseClosureService $courseClosureService
    ) {}

    /**
     * GET /subjects
     * Listar todas las materias
     */
    public function index(Request $request)
    {
        if ($request->has('count_only')) {
            return $this->success(['total' => Subject::count()]);
        }

        $cacheKey = 'cache:subjects:all';
        
        $subjects = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return Subject::all();
        });
        
        $subjectsData = SubjectResource::collection($subjects)->resolve();
        
        return response()->json([
            'data' => $subjectsData
        ]);
    }

    /**
     * POST /subjects
     * Crear materia (solo admin)
     */
    public function store(StoreSubjectRequest $request)
    {
        $this->authorize('create', Subject::class);
        
        $subject = $this->subjectService->create($request->validated());

        return response()->json([
            'data' => new SubjectResource($subject),
            'message' => 'Materia creada correctamente'
        ], 201);
    }

    /**
     * GET /subjects/{subject}
     * Ver materia específica
     */
    public function show(Subject $subject)
    {
        $this->authorize('view', $subject);
        
        $user = request()->user();
        
        // Si es estudiante, agregar info de sección
        if ($user->role?->name === 'estudiante') {
            $data = $this->subjectService->getSubjectForStudent($subject, $user);
            // Estandarizado: envolver en data
            return response()->json(['data' => $data]);
        }
        
        // Para otros roles, usamos el Resource
        return response()->json([
            'data' => new SubjectResource($subject)
        ]);
    }

    /**
     * PUT /subjects/{subject}
     * Actualizar materia (solo admin)
     */
    public function update(UpdateSubjectRequest $request, Subject $subject)
    {
        $this->authorize('update', $subject);
        
        $subject = $this->subjectService->update($subject, $request->validated());

        return response()->json([
            'data' => new SubjectResource($subject),
            'message' => 'Materia actualizada correctamente'
        ]);
    }

    /**
     * DELETE /subjects/{subject}
     * Eliminar materia (solo admin)
     */
    public function destroy(Subject $subject)
    {
        $this->authorize('delete', $subject);
        
        $this->subjectService->delete($subject);

        return response()->json(['message' => 'Materia eliminada correctamente']);
    }

    /**
     * GET /my-subjects
     * Obtener materias del profesor autenticado
     */
    public function mySubjects()
    {
        // Authorization ya verificada por route middleware (role:profesor)
        
        $user = request()->user();
        $data = $this->subjectService->getSubjectsForTeacher($user);

        return response()->json($data);
    }

    /**
     * GET /my-subjects-student
     * Obtener materias del estudiante autenticado
     */
    public function mySubjectsForStudent()
    {
        // Authorization ya verificada por route middleware (role:estudiante)
        
        $user = request()->user();
        $data = $this->subjectService->getSubjectsForStudent($user);

        return response()->json($data);
    }

    /**
     * GET /subjects/{subject}/participantes
     * Obtener participantes de una materia
     */
    public function participantes(Subject $subject)
    {
        $this->authorize('viewParticipantes', $subject);
        
        try {
            $user = request()->user();
            $data = $this->subjectService->getParticipantes($subject, $user);

            // Con ?page= el servicio devuelve {data, meta}; sin paginar, la lista directa
            if (isset($data['data'])) {
                return $this->success($data['data'], null, $data['meta'] ?? []);
            }
            return $this->success($data);

        } catch (\Exception $e) {
            Log::error('Error en participantes: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al obtener participantes'
            ], 500);
        }
    }

    /**
     * POST /subjects/{subject}/sections/{section}/close
     * Cerrar curso masivo para todos los estudiantes de una materia+sección
     */
    public function closeCourse(Subject $subject, Section $section, Request $request)
    {
        // Validar que el profesor dicta esta materia en esta sección
        $user = $request->user();
        
        $isProfessorOfCourse = \App\Models\ClassSchedule::where('subject_id', $subject->id)
            ->where('section_id', $section->id)
            ->where('teacher_id', $user->id)
            ->exists();

        if (!$isProfessorOfCourse && !$user->isAdmin()) {
            return response()->json([
                'message' => 'No tienes autorización para cerrar esta materia'
            ], 403);
        }

        // Validar que la sección pertenece a la materia
        $hasSubjectInSection = \App\Models\ClassSchedule::where('subject_id', $subject->id)
            ->where('section_id', $section->id)
            ->exists();

        if (!$hasSubjectInSection) {
            return response()->json([
                'message' => 'Esta materia no está asignada a la sección seleccionada'
            ], 422);
        }

        // Obtener opciones
        $observations = $request->input('observations');
        $options = $observations ? ['observations' => $observations] : [];

        try {
            $result = $this->courseClosureService->closeCourseMassive(
                $subject,
                $section,
                $user,
                $options
            );

            return $this->success([
                'processed_students' => $result['processed_students'],
                'total_students' => $result['total_students'],
                'errors' => $result['errors'] ?? [],
            ], $result['message']);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // El curso ya fue cerrado - devolver mensaje claro
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error al cerrar curso: ' . $e->getMessage());

            return response()->json([
                'message' => 'Error al cerrar el curso'
            ], 500);
        }
    }

    /**
     * POST /students/{student}/courses/close
     * Cerrar curso para un estudiante específico
     */
    public function closeStudentCourse(Request $request, User $student, Subject $subject, Section $section)
    {
        $user = $request->user();

        // Validar rol de estudiante
        if (!$student->isStudent()) {
            return response()->json([
                'message' => 'El usuario seleccionado no es un estudiante'
            ], 422);
        }

        // Validar que el profesor dicta esta materia
        $isProfessorOfCourse = \App\Models\ClassSchedule::where('subject_id', $subject->id)
            ->where('section_id', $section->id)
            ->where('teacher_id', $user->id)
            ->exists();

        if (!$isProfessorOfCourse && !$user->isAdmin()) {
            return response()->json([
                'message' => 'No tienes autorización para cerrar este curso'
            ], 403);
        }

        $observations = $request->input('observations');
        $options = $observations ? ['observations' => $observations] : [];

        try {
            $course = $this->courseClosureService->closeCourseForStudent(
                $student,
                $subject,
                $section,
                $user,
                $options
            );

            return response()->json([
                'message' => 'Curso cerrado correctamente para el estudiante',
                'data' => [
                    'student_id' => $student->id,
                    'subject_id' => $subject->id,
                    'final_grade' => $course->final_grade,
                    'status' => $course->status,
                    'closed_at' => $course->closed_at
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error al cerrar curso para estudiante: ' . $e->getMessage());

            return response()->json([
                'message' => 'Error al cerrar el curso'
            ], 500);
        }
    }

    /**
     * GET /subjects/{subject}/sections/{section}/status
     * Verificar si el curso ya fue cerrado
     */
    public function getCourseStatus(Subject $subject, Section $section)
    {
        $schoolYear = $section->schoolYear;
        
        if (!$schoolYear) {
            return $this->success(['is_closed' => false]);
        }

        $alreadyClosed = $this->courseClosureService->isCourseAlreadyClosedForSubject(
            $subject->id,
            $section->id,
            $schoolYear->id
        );

        return $this->success(['is_closed' => $alreadyClosed]);
    }

    /**
     * GET /admin/courses/pending-closure
     * Listar cursos pendientes de cierre para el admin.
     * Retorna materias+secciones que tienen estudiantes activos pero no han sido cerradas.
     */
    public function listPendingClosures(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $schoolYearId = $request->query('school_year_id');
        $gradeId = $request->query('grade_id');
        $sectionId = $request->query('section_id');

        // Obtener todas las secciones con sus relaciones
        $sectionsQuery = Section::with(['grade', 'schoolYear']);

        if ($schoolYearId) {
            $sectionsQuery->where('school_year_id', $schoolYearId);
        }
        if ($gradeId) {
            $sectionsQuery->where('grade_id', $gradeId);
        }
        if ($sectionId) {
            $sectionsQuery->where('id', $sectionId);
        }

        $sections = $sectionsQuery->orderBy('grade_id')->orderBy('name')->get();

        $pendingCourses = [];

        foreach ($sections as $section) {
            // Obtener materias asignadas a esta sección (vía class_schedules)
            $subjectIds = \App\Models\ClassSchedule::where('section_id', $section->id)
                ->distinct()
                ->pluck('subject_id');

            $subjects = Subject::whereIn('id', $subjectIds)->get();

            foreach ($subjects as $subject) {
                $isClosed = $this->courseClosureService->isCourseAlreadyClosedForSubject(
                    $subject->id,
                    $section->id,
                    $section->school_year_id
                );

                // Contar estudiantes activos en esta materia+sección
                $studentCount = $this->courseClosureService->getStudentsForCourse($subject, $section)->count();

                // Obtener profesor de la materia
                $professor = $this->courseClosureService->getCourseProfessor($subject, $section);

                $pendingCourses[] = [
                    'subject_id' => $subject->id,
                    'subject_name' => $subject->name,
                    'section_id' => $section->id,
                    'section_name' => $section->name,
                    'grade_id' => $section->grade_id,
                    'grade_name' => $section->grade?->name,
                    'school_year_id' => $section->school_year_id,
                    'school_year_name' => $section->schoolYear?->name,
                    'is_closed' => $isClosed,
                    'student_count' => $studentCount,
                    'professor' => $professor
                        ? trim($professor->first_name . ' ' . $professor->last_name)
                        : 'Sin asignar',
                ];
            }
        }

        return response()->json(['data' => $pendingCourses]);
    }

    /**
     * GET /students/{student}/courses
     * Obtener historial académico del estudiante
     * 
     * Control de acceso (FASE 1):
     * - Admin: acceso total
     * - Estudiante: solo su propio historial
     * - Profesor: solo estudiantes de sus secciones
     */
    public function getStudentCourses(User $student, Request $request)
    {
        $currentUser = $request->user();
        
        // F1: Control de acceso - denied by default
        $canViewGrades = false;
        
        if ($currentUser->isAdmin()) {
            // Admin tiene acceso total
            $canViewGrades = true;
        } elseif ($currentUser->id === $student->id) {
            // Estudiante viendo su propio historial
            $canViewGrades = true;
        } elseif ($currentUser->isTeacher()) {
            // F1: Verificar que el profesor enseña a este estudiante
            // El estudiante debe estar en una sección donde el profesor enseña
            $teachesThisStudent = \App\Models\ClassSchedule::where('teacher_id', $currentUser->id)
                ->where('section_id', $student->section_id)
                ->exists();
            
            if ($teachesThisStudent) {
                $canViewGrades = true;
            }
        }

        $courses = $this->courseClosureService->getStudentHistory($student->id);

        // Transformar respuesta según visibilidad
        $data = $courses->map(function ($course) use ($canViewGrades) {
            $item = [
                'subject' => $course->subject->name,
                'section' => $course->section->name,
                'school_year' => $course->schoolYear->name,
                'status' => $course->status,
                'closed_at' => $course->closed_at?->toDateString(),
            ];

            // Solo agregar final_grade si el usuario puede verlo
            if ($canViewGrades) {
                $item['final_grade'] = $course->final_grade;
                $item['parcial_grades'] = $course->parcial_grades ?? [];
                $item['total_score_obtained'] = $course->total_score_obtained;
                $item['total_score_possible'] = $course->total_score_possible;
                $item['passing_percentage'] = $course->passing_percentage;
                $item['profesor'] = $course->profesor?->first_name . ' ' . $course->profesor?->last_name;
                $item['closed_by'] = $course->closedBy?->first_name . ' ' . $course->closedBy?->last_name;
            }

            return $item;
        });

        return $this->success($data, null, ['can_view_grades' => $canViewGrades]);
    }
}
