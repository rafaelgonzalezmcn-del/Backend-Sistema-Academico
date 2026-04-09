<?php

namespace App\Services;

use App\Models\User;
use App\Models\Grade;
use App\Models\SchoolYear;
use App\Models\StudentCourse;
use App\Models\Subject;
use App\Models\Section;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * PromotionService — Centraliza toda la lógica de elegibilidad para promoción.
 *
 * Regla de negocio:
 * Un estudiante es elegible para promoción ÚNICAMENTE si ha aprobado
 * el 100% de las materias asignadas a su grado en el año lectivo actual.
 *
 * No se permite promoción con:
 * - Materias reprobadas
 * - Materias pendientes (cursando o sin curso registrado)
 */
class PromotionService
{
    /**
     * Verificar elegibilidad de un estudiante para promoción.
     *
     * @param int $studentId ID del estudiante
     * @param int|null $schoolYearId Año lectivo a evaluar (null = año activo)
     * @return array Estructura completa de elegibilidad
     */
    public function checkEligibility(int $studentId, ?int $schoolYearId = null): array
    {
        // 1. Obtener estudiante
        $student = User::with(['role', 'section.grade', 'section.schoolYear'])->find($studentId);

        if (!$student) {
            return $this->errorResponse('El estudiante no existe');
        }

        if (!$student->isStudent()) {
            return $this->errorResponse('El usuario no es un estudiante');
        }

        // F10-H4: Validar consistencia de datos
        if (!$student->section_id || !$student->section?->grade_id) {
            return $this->errorResponse('Datos académicos incompletos para evaluar');
        }

        // 2. Determinar año lectivo
        $activeYear = $schoolYearId
            ? SchoolYear::find($schoolYearId)
            : SchoolYear::where('active', true)->first();

        if (!$activeYear) {
            return $this->errorResponse('No hay año lectivo activo para evaluar');
        }

        // 3. Obtener grado actual del estudiante
        $currentGrade = $student->section->grade;

        // F10-H6: Validar que el grado tiene orden definido
        if (!$currentGrade) {
            return $this->errorResponse('Datos académicos incompletos para evaluar');
        }

        // 4. Obtener materias del grado actual (vía class_schedules del año activo)
        $subjectIds = $this->getGradeSubjectIds($currentGrade->id, $activeYear->id);

        if ($subjectIds->isEmpty()) {
            return $this->errorResponse('No hay materias asignadas al grado actual en este año lectivo');
        }

        // F10-H1: Filtrar cursos SOLO del año lectivo activo
        $courses = StudentCourse::with(['subject:id,name', 'schoolYear:id,name', 'section:id,name', 'profesor:id,first_name,last_name'])
            ->where('student_id', $studentId)
            ->where('school_year_id', $activeYear->id)
            ->get();

        // 5. Evaluar cursos (método reutilizable para F10-H7 / Fase 11 batch)
        $evaluation = $this->evaluateCourses($courses, $subjectIds);

        // 6. Determinar elegibilidad
        $eligible = $this->determineEligibility($evaluation, $subjectIds->count());

        // 7. Resolver siguiente grado
        $nextGrade = $this->resolveNextGrade($currentGrade);

        // 8. Construir respuesta
        return $this->buildResponse(
            $student,
            $currentGrade,
            $nextGrade,
            $evaluation,
            $subjectIds->count(),
            $eligible
        );
    }

    /**
     * F10-H7: Método reutilizable para evaluar cursos.
     * Extraído para ser usado en checkEligibility() y futuro checkEligibilityBatch().
     *
     * @param Collection $courses StudentCourse del estudiante
     * @param Collection $requiredSubjectIds IDs de materias requeridas del grado
     * @return array Evaluación completa con stats y detalles
     */
    public function evaluateCourses(Collection $courses, Collection $requiredSubjectIds): array
    {
        // Precargar materias faltantes en una sola query (evita N+1)
        $existingSubjectIds = $courses->pluck('subject_id')->unique();
        $missingSubjectIds = $requiredSubjectIds->diff($existingSubjectIds);
        $missingSubjects = Subject::whereIn('id', $missingSubjectIds)->get()->keyBy('id');

        // Indexar cursos por subject_id para acceso rápido O(1)
        $coursesBySubject = $courses->keyBy('subject_id');

        $approved = 0;
        $failed = 0;
        $pending = 0;
        $totalGrade = 0;
        $gradedCount = 0;
        $details = [];

        foreach ($requiredSubjectIds as $subjectId) {
            $course = $coursesBySubject->get($subjectId);

            if ($course) {
                $subjectName = $course->subject?->name ?? 'Materia desconocida';

                // F10-H3: Definición clara de pendiente
                // Pendiente = no cerrado (cursando) o sin nota final
                if (!$course->isClosed() || $course->status === 'cursando') {
                    $pending++;
                    $details[] = [
                        'subject' => $subjectName,
                        'subject_id' => $subjectId,
                        'status' => 'pendiente',
                        'grade' => $course->final_grade,
                        'school_year' => $course->schoolYear?->name,
                        'section' => $course->section?->name,
                        'profesor' => $course->profesor
                            ? trim($course->profesor->first_name . ' ' . $course->profesor->last_name)
                            : 'N/A',
                    ];
                } elseif ($course->status === 'reprobado') {
                    $failed++;
                    if ($course->final_grade !== null) {
                        $totalGrade += (float) $course->final_grade;
                        $gradedCount++;
                    }
                    $details[] = [
                        'subject' => $subjectName,
                        'subject_id' => $subjectId,
                        'status' => 'reprobado',
                        'grade' => $course->final_grade,
                        'school_year' => $course->schoolYear?->name,
                        'section' => $course->section?->name,
                        'profesor' => $course->profesor
                            ? trim($course->profesor->first_name . ' ' . $course->profesor->last_name)
                            : 'N/A',
                        'closed_at' => $course->closed_at?->format('Y-m-d'),
                    ];
                } else {
                    // aprobado, concluido
                    $approved++;
                    if ($course->final_grade !== null) {
                        $totalGrade += (float) $course->final_grade;
                        $gradedCount++;
                    }
                    $details[] = [
                        'subject' => $subjectName,
                        'subject_id' => $subjectId,
                        'status' => 'aprobado',
                        'grade' => $course->final_grade,
                        'school_year' => $course->schoolYear?->name,
                        'section' => $course->section?->name,
                        'profesor' => $course->profesor
                            ? trim($course->profesor->first_name . ' ' . $course->profesor->last_name)
                            : 'N/A',
                        'closed_at' => $course->closed_at?->format('Y-m-d'),
                    ];
                }
            } else {
                // F10-H2: Materia requerida sin curso registrado → pendiente
                $pending++;
                $subject = $missingSubjects->get($subjectId);
                $details[] = [
                    'subject' => $subject?->name ?? 'Materia desconocida',
                    'subject_id' => $subjectId,
                    'status' => 'pendiente',
                    'grade' => null,
                ];
            }
        }

        return [
            'approved' => $approved,
            'failed' => $failed,
            'pending' => $pending,
            'average_grade' => $gradedCount > 0 ? round($totalGrade / $gradedCount, 1) : null,
            'details' => $details,
        ];
    }

    /**
     * Obtener IDs de materias asignadas al grado en el año lectivo.
     * Usa class_schedules para determinar qué materias se dictan en ese grado.
     * F10-H1: Filtra estrictamente por school_year_id.
     */
    private function getGradeSubjectIds(int $gradeId, int $schoolYearId): Collection
    {
        return \App\Models\ClassSchedule::whereHas('section', function ($q) use ($gradeId, $schoolYearId) {
                $q->where('grade_id', $gradeId)
                  ->where('school_year_id', $schoolYearId);
            })
            ->distinct()
            ->pluck('subject_id');
    }

    /**
     * Determinar si el estudiante es elegible para promoción.
     *
     * Regla: 100% de materias aprobadas.
     * - Si hay al menos 1 reprobada → NO elegible
     * - Si hay al menos 1 pendiente → NO elegible
     * - Si todas aprobadas → ELEGIBLE
     */
    private function determineEligibility(array $evaluation, int $totalRequired): bool
    {
        return $evaluation['approved'] === $totalRequired
            && $evaluation['failed'] === 0
            && $evaluation['pending'] === 0;
    }

    /**
     * Resolver el siguiente grado en la secuencia.
     * F10-H6: Usa Grade::nextGrade() que ya maneja null correctamente.
     * Retorna null si es el último grado o si no tiene grade_order.
     */
    private function resolveNextGrade(Grade $currentGrade): ?Grade
    {
        return $currentGrade->nextGrade();
    }

    /**
     * Construir respuesta estructurada.
     * F10-H5: Incluye average_grade y total_subjects.
     */
    private function buildResponse(
        User $student,
        Grade $currentGrade,
        ?Grade $nextGrade,
        array $evaluation,
        int $totalRequired,
        bool $eligible
    ): array {
        $message = null;

        if ($eligible) {
            $message = 'El estudiante ha aprobado el 100% de las materias y es elegible para promoción.';
        } elseif ($evaluation['failed'] > 0) {
            $failedSubjects = collect($evaluation['details'])
                ->where('status', 'reprobado')
                ->pluck('subject')
                ->join(', ');
            $message = "El estudiante tiene {$evaluation['failed']} materia(s) reprobada(s): {$failedSubjects}";
        } elseif ($evaluation['pending'] > 0) {
            $pendingSubjects = collect($evaluation['details'])
                ->where('status', 'pendiente')
                ->pluck('subject')
                ->join(', ');
            $message = "El estudiante tiene {$evaluation['pending']} materia(s) pendiente(s): {$pendingSubjects}";
        }

        return [
            'eligible' => $eligible,
            'message' => $message,
            'current_grade' => $currentGrade->name,
            'next_grade' => $nextGrade?->name,
            'next_grade_id' => $nextGrade?->id,
            'subjects_approved' => $evaluation['approved'],
            'subjects_failed' => $evaluation['failed'],
            'subjects_pending' => $evaluation['pending'],
            'total_subjects' => $totalRequired,
            'average_grade' => $evaluation['average_grade'],
            'approval_percentage' => $totalRequired > 0
                ? round(($evaluation['approved'] / $totalRequired) * 100, 1)
                : 0,
            'details' => $evaluation['details'],
        ];
    }

    /**
     * F11-T1: Promover un estudiante al siguiente grado.
     *
     * Reglas críticas:
     * - Valida elegibilidad con checkEligibility()
     * - Soporta dos tipos: 'promote' (siguiente grado) y 'repeat' (mismo grado)
     * - Determina automáticamente el año lectivo destino (siguiente al actual)
     * - Valida que la sección destino pertenezca al año lectivo destino
     * - Usa DB::transaction() para atomicidad
     * - Cierra cursos actuales como 'concluido'
     * - Actualiza section_id del estudiante
     * - Registra log estructurado de auditoría
     *
     * @param int $studentId ID del estudiante
     * @param int $targetSectionId ID de la sección destino
     * @param string $moveType 'promote' (siguiente grado) o 'repeat' (mismo grado)
     * @param string|null $observations Observaciones opcionales
     * @return array Resultado de la promoción
     * @throws ValidationException si no es elegible o hay error de validación
     * @throws \Exception si hay error de datos
     */
    public function promoteStudent(int $studentId, int $targetSectionId, string $moveType = 'promote', ?string $observations = null): array
    {
        // 1. Obtener estudiante con relaciones
        $student = User::with(['section.grade', 'section.schoolYear'])->find($studentId);

        if (!$student) {
            throw new \Exception('El estudiante no existe');
        }

        if (!$student->isStudent()) {
            throw new \Exception('El usuario no es un estudiante');
        }

        // Validar consistencia de datos
        if (!$student->section_id || !$student->section?->grade_id) {
            throw new \Exception('Datos académicos incompletos para promover');
        }

        $currentGrade = $student->section->grade;
        $currentSchoolYear = $student->section->schoolYear;

        if (!$currentSchoolYear) {
            throw new \Exception('No se puede determinar el año lectivo actual del estudiante');
        }

        // 3. Determinar año lectivo destino (siempre el siguiente al actual)
        $targetSchoolYear = $currentSchoolYear->nextYear();

        if (!$targetSchoolYear) {
            throw ValidationException::withMessages([
                'school_year' => 'No existe un año lectivo siguiente configurado. Contacte al administrador.',
            ]);
        }

        // 4. Verificar que TODAS las materias estén cerradas
        $totalCourses = StudentCourse::where('student_id', $studentId)
            ->where('school_year_id', $currentSchoolYear->id)
            ->count();

        if ($totalCourses > 0) {
            $openCourses = StudentCourse::where('student_id', $studentId)
                ->where('school_year_id', $currentSchoolYear->id)
                ->whereNull('closed_at')
                ->count();

            if ($openCourses > 0) {
                throw ValidationException::withMessages([
                    'promotion' => "El estudiante tiene {$openCourses} materia(s) sin cerrar. Cierre todas las materias antes de promover.",
                ]);
            }
        }

        // 5. Verificar elegibilidad SOLO para move_type='promote'
        // Para 'repeat', no se requiere elegibilidad (el estudiante repite porque reprobó)
        if ($moveType === 'promote') {
            $eligibility = $this->checkEligibility($studentId, $currentSchoolYear->id);

            if (!$eligibility['eligible']) {
                throw ValidationException::withMessages([
                    'promotion' => $eligibility['message'] ?? 'El estudiante no es elegible para promoción',
                ]);
            }
        }

        // 6. Validar sección destino
        $targetSection = Section::with(['grade', 'schoolYear'])->find($targetSectionId);

        if (!$targetSection) {
            throw new \Exception('La sección destino no existe');
        }

        // H11-EX3: Validar capacidad de sección destino
        if ($targetSection->max_capacity !== null) {
            $enrolledCount = User::where('section_id', $targetSectionId)
                ->where('activo', true)
                ->whereHas('role', fn($q) => $q->where('name', 'estudiante'))
                ->count();

            if ($enrolledCount >= $targetSection->max_capacity) {
                throw ValidationException::withMessages([
                    'promotion' => "La sección destino ha alcanzado su capacidad máxima ({$enrolledCount}/{$targetSection->max_capacity})",
                ]);
            }
        }

        // Validar que la sección destino pertenece al año lectivo destino
        if ($targetSection->school_year_id !== $targetSchoolYear->id) {
            throw ValidationException::withMessages([
                'promotion' => "La sección destino '{$targetSection->name}' pertenece al año '{$targetSection->schoolYear?->name}', pero debe pertenecer a '{$targetSchoolYear->name}'",
            ]);
        }

        // Validar coherencia de grado según move_type
        $nextGrade = $this->resolveNextGrade($currentGrade);

        if ($moveType === 'promote') {
            if (!$nextGrade) {
                throw new \Exception('No hay un siguiente grado definido para este estudiante');
            }

            if ($targetSection->grade_id !== $nextGrade->id) {
                throw ValidationException::withMessages([
                    'promotion' => "Para promover, la sección debe ser del siguiente grado ('{$nextGrade->name}'), pero la sección seleccionada es de '{$targetSection->grade?->name}'",
                ]);
            }
        } elseif ($moveType === 'repeat') {
            if ($targetSection->grade_id !== $currentGrade->id) {
                throw ValidationException::withMessages([
                    'promotion' => "Para repetir, la sección debe ser del mismo grado ('{$currentGrade->name}'), pero la sección seleccionada es de '{$targetSection->grade?->name}'",
                ]);
            }
        } else {
            throw ValidationException::withMessages([
                'promotion' => "Tipo de movimiento inválido. Debe ser 'promote' o 'repeat'",
            ]);
        }

        // F11-EX1: Verificar que no ya está en esa sección
        if ($student->section_id === $targetSectionId) {
            throw ValidationException::withMessages([
                'promotion' => 'El estudiante ya pertenece a esta sección',
            ]);
        }

        // 5. Ejecutar promoción en transacción
        return DB::transaction(function () use ($student, $targetSection, $currentSchoolYear, $targetSchoolYear, $moveType, $observations) {
            // 5a. Cerrar cursos del año lectivo actual como 'concluido'
            StudentCourse::where('student_id', $student->id)
                ->where('school_year_id', $currentSchoolYear->id)
                ->where('status', 'cursando')
                ->update([
                    'status' => 'concluido',
                    'observations' => $observations ?: ($moveType === 'repeat' ? 'Concluido por repetición de grado' : 'Concluido por promoción automática'),
                    'closed_at' => now(),
                ]);

            // 5b. Actualizar sección del estudiante
            $oldSectionId = $student->section_id;
            $oldGradeName = $student->section->grade?->name;
            $student->update(['section_id' => $targetSection->id]);

            // H11-EX5: Logging estructurado completo para auditoría
            Log::channel('daily')->info('student_promoted', [
                'event' => 'student_promotion',
                'student_id' => $student->id,
                'student_name' => trim($student->first_name . ' ' . $student->last_name),
                'move_type' => $moveType,
                'from_section_id' => $oldSectionId,
                'from_grade' => $oldGradeName,
                'from_school_year' => $currentSchoolYear?->name,
                'to_section_id' => $targetSection->id,
                'to_grade' => $targetSection->grade?->name,
                'to_school_year' => $targetSchoolYear?->name,
                'performed_by' => request()->user()?->id,
                'observations' => $observations,
                'timestamp' => now()->toIso8601String(),
            ]);

            return [
                'success' => true,
                'student_id' => $student->id,
                'student_name' => trim($student->first_name . ' ' . $student->last_name),
                'move_type' => $moveType,
                'from_section_id' => $oldSectionId,
                'to_section_id' => $targetSection->id,
                'from_grade' => $oldGradeName,
                'to_grade' => $targetSection->grade?->name,
                'from_school_year' => $currentSchoolYear?->name,
                'to_school_year' => $targetSchoolYear?->name,
                'observations' => $observations,
            ];
        });
    }

    /**
     * F11-T7: Promoción masiva de todos los estudiantes elegibles de una sección.
     * H11-EX10: Siempre retorna estructura completa.
     * H11-EX11: Nunca lanza excepción total — siempre soft-fail.
     *
     * @param int $sectionId ID de la sección origen
     * @param int $targetGradeId ID del grado destino
     * @param string $moveType 'promote' o 'repeat'
     * @param string|null $observations Observaciones para la promoción
     * @return array Resultado estructurado siempre
     */
    public function promoteSection(int $sectionId, int $targetGradeId, string $moveType = 'promote', ?string $observations = null): array
    {
        $section = Section::with(['grade', 'schoolYear'])->find($sectionId);

        if (!$section) {
            return [
                'promoted' => 0,
                'skipped' => 0,
                'total' => 0,
                'details' => [['student_id' => null, 'student_name' => 'N/A', 'status' => 'error', 'reason' => 'La sección no existe']],
            ];
        }

        // Determinar año lectivo destino
        $targetSchoolYear = $section->schoolYear?->nextYear();

        if (!$targetSchoolYear) {
            return [
                'promoted' => 0,
                'skipped' => 0,
                'total' => 0,
                'details' => [['student_id' => null, 'student_name' => 'N/A', 'status' => 'error', 'reason' => 'No hay año lectivo siguiente configurado']],
            ];
        }

        // Obtener estudiantes activos de la sección
        $students = User::where('section_id', $sectionId)
            ->where('activo', true)
            ->whereHas('role', fn($q) => $q->where('name', 'estudiante'))
            ->get();

        $promoted = 0;
        $skipped = 0;
        $details = [];

        foreach ($students as $student) {
            $studentName = trim($student->first_name . ' ' . $student->last_name);

            try {
                // Evaluar elegibilidad SOLO para move_type='promote'
                if ($moveType === 'promote') {
                    $eligibility = $this->checkEligibility($student->id, $section->schoolYear?->id);

                    if (!$eligibility['eligible']) {
                        $skipped++;
                        $details[] = [
                            'student_id' => $student->id,
                            'student_name' => $studentName,
                            'status' => 'skipped',
                            'reason' => $eligibility['message'] ?? 'No elegible',
                        ];
                        continue;
                    }
                }

                // Buscar sección destino en el grado correcto y año destino
                $targetSection = Section::where('grade_id', $targetGradeId)
                    ->where('school_year_id', $targetSchoolYear->id)
                    ->first();

                if (!$targetSection) {
                    $details[] = [
                        'student_id' => $student->id,
                        'student_name' => $studentName,
                        'status' => 'skipped',
                        'reason' => "No hay sección disponible en el grado destino para el año {$targetSchoolYear->name}",
                    ];
                    $skipped++;
                    continue;
                }

                // Validar capacidad de sección destino
                if ($targetSection->max_capacity !== null) {
                    $enrolledCount = User::where('section_id', $targetSection->id)
                        ->where('activo', true)
                        ->whereHas('role', fn($q) => $q->where('name', 'estudiante'))
                        ->count();

                    if ($enrolledCount >= $targetSection->max_capacity) {
                        $details[] = [
                            'student_id' => $student->id,
                            'student_name' => $studentName,
                            'status' => 'skipped',
                            'reason' => "Sección destino llena ({$enrolledCount}/{$targetSection->max_capacity})",
                        ];
                        $skipped++;
                        continue;
                    }
                }

                // Promover individual (con transacción interna)
                $this->promoteStudent($student->id, $targetSection->id, $moveType, $observations ?: 'Promoción masiva');
                $promoted++;
                $details[] = [
                    'student_id' => $student->id,
                    'student_name' => $studentName,
                    'status' => 'promoted',
                    'from_section' => $section->name,
                    'to_section' => $targetSection->name,
                ];

            } catch (\Exception $e) {
                // H11-H7: Soft-fail — no romper el proceso completo
                $details[] = [
                    'student_id' => $student->id,
                    'student_name' => $studentName,
                    'status' => 'error',
                    'reason' => $e instanceof ValidationException
                        ? collect($e->errors())->flatten()->join(', ')
                        : $e->getMessage(),
                ];
                $skipped++;
            }
        }

        return [
            'promoted' => $promoted,
            'skipped' => $skipped,
            'total' => $students->count(),
            'details' => $details,
        ];
    }

    /**
     * Respuesta de error estandarizada.
     */
    private function errorResponse(string $message): array
    {
        return [
            'eligible' => false,
            'message' => $message,
            'current_grade' => null,
            'next_grade' => null,
            'next_grade_id' => null,
            'subjects_approved' => 0,
            'subjects_failed' => 0,
            'subjects_pending' => 0,
            'total_subjects' => 0,
            'average_grade' => null,
            'approval_percentage' => 0,
            'details' => [],
        ];
    }
}
