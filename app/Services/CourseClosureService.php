<?php

namespace App\Services;

use App\Models\StudentCourse;
use App\Models\Subject;
use App\Models\User;
use App\Models\Entrega;
use App\Models\ClassSchedule;
use App\Models\Section;
use App\Models\Parcial;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseClosureService
{
    const STATUS_CURSANDO = 'cursando';
    const STATUS_APROBADO = 'aprobado';
    const STATUS_REPROBADO = 'reprobado';
    const STATUS_CONCLUIDO = 'concluido';
    const STATUS_RETIRADO = 'retirado';
    
    // Porcentaje mínimo para aprobar (70%)
    const PASSING_PERCENTAGE = 70.00;

    /**
     * Cierre masivo para todos los estudiantes de una materia+sección
     * 
     * FLUJO: Sin éxito parcial - si falla uno, falla todo
     */
    public function closeCourseMassive(
        Subject $subject,
        Section $section,
        User $profesor,
        array $options = []
    ): array {
        $schoolYear = $section->schoolYear;
        
        if (!$schoolYear) {
            throw ValidationException::withMessages([
                'section' => 'La sección no tiene año lectivo asignado'
            ]);
        }

        $students = $this->getStudentsForCourse($subject, $section);
        
        if ($students->isEmpty()) {
            throw ValidationException::withMessages([
                'students' => 'No hay estudiantes en esta materia'
            ]);
        }

        // VERIFICACIÓN GLOBAL antes de procesar CUALQUIER estudiante
        $alreadyClosed = $this->isCourseAlreadyClosedForSubject(
            $subject->id,
            $section->id,
            $schoolYear->id
        );

        if ($alreadyClosed) {
            throw ValidationException::withMessages([
                'course' => 'Este curso ya fue cerrado y no puede modificarse'
            ]);
        }

        // TRANSACCIÓN única - si falla uno, revierte TODO
        return DB::transaction(function () use ($subject, $section, $schoolYear, $profesor, $options, $students) {
            $processed = 0;
            $now = now()->toDateString();
            $courseProfessor = $this->getCourseProfessor($subject, $section);

            foreach ($students as $student) {
                // Calcular nota FINAL del estudiante - теперь возвращает массив
                $gradeData = $this->calculateFinalGrade($student->id, $subject->id);
                
                // Calcular notas por parcial
                $parcialGrades = $gradeData['parciales'] ?? [];
                
                // Determinar estado basado en porcentaje (70%)
                $status = $this->determineStatus($gradeData);
                
                // Extraer datos para guardar
                $finalGrade = $gradeData['percentage'] ?? null;
                $totalObtained = $gradeData['total_obtained'] ?? null;
                $totalPossible = $gradeData['total_possible'] ?? null;

                // Observaciones
                $observations = $options['observations'] ?? null;

                // SOLO CREATE - sin update, sin updateOrCreate
                // Si falla (ej: constraint único), la excepción sube y hace rollback
                StudentCourse::create([
                    'student_id' => $student->id,
                    'subject_id' => $subject->id,
                    'section_id' => $section->id,
                    'school_year_id' => $schoolYear->id,
                    'profesor_id' => $courseProfessor?->id,
                    'final_grade' => $finalGrade,
                    'parcial_grades' => $parcialGrades,
                    'total_score_obtained' => $totalObtained,
                    'total_score_possible' => $totalPossible,
                    'passing_percentage' => self::PASSING_PERCENTAGE,
                    'status' => $status,
                    'observations' => $observations,
                    'closed_at' => $now,
                    'closed_by_user_id' => $profesor->id,
                ]);

                $processed++;
            }

            // Solo llega aquí si todos los estudiantes se procesaron exitosamente
            return [
                'message' => 'Curso cerrado correctamente',
                'processed_students' => $processed,
                'total_students' => $students->count(),
                'errors' => []
            ];
        });
    }

    /**
     * Cierre para un estudiante específico
     */
    public function closeCourseForStudent(
        User $student,
        Subject $subject,
        Section $section,
        User $profesor,
        array $options = []
    ): StudentCourse {
        $schoolYear = $section->schoolYear;
        
        if (!$schoolYear) {
            throw ValidationException::withMessages([
                'section' => 'La sección no tiene año lectivo asignado'
            ]);
        }

        // VERIFICACIÓN GLOBAL
        $alreadyClosed = $this->isCourseAlreadyClosedForSubject(
            $subject->id,
            $section->id,
            $schoolYear->id
        );

        if ($alreadyClosed) {
            throw ValidationException::withMessages([
                'course' => 'Este curso ya fue cerrado y no puede modificarse'
            ]);
        }

        $gradeData = $this->calculateFinalGrade($student->id, $subject->id);
        $parcialGrades = $gradeData['parciales'] ?? [];
        $status = $this->determineStatus($gradeData);
        $courseProfessor = $this->getCourseProfessor($subject, $section);
        
        // Extraer datos para guardar
        $finalGrade = $gradeData['percentage'] ?? null;
        $totalObtained = $gradeData['total_obtained'] ?? null;
        $totalPossible = $gradeData['total_possible'] ?? null;

        // SOLO CREATE
        return StudentCourse::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'profesor_id' => $courseProfessor?->id,
            'final_grade' => $finalGrade,
            'parcial_grades' => $parcialGrades,
            'total_score_obtained' => $totalObtained,
            'total_score_possible' => $totalPossible,
            'passing_percentage' => self::PASSING_PERCENTAGE,
            'status' => $status,
            'observations' => $options['observations'] ?? null,
            'closed_at' => now()->toDateString(),
            'closed_by_user_id' => $profesor->id,
        ]);
    }

    /**
     * Obtener estudiantes de la materia+sección
     */
    public function getStudentsForCourse(Subject $subject, Section $section)
    {
        return $subject->estudiantes()
            ->where('section_id', $section->id)
            ->get();
    }

    /**
     * VERIFICACIÓN GLOBAL: ¿El curso ya fue cerrado?
     * Retorna true si existe ALGÚN registro con status != 'cursando'
     */
    public function isCourseAlreadyClosedForSubject(
        int $subjectId,
        int $sectionId,
        int $schoolYearId
    ): bool {
        return StudentCourse::where('subject_id', $subjectId)
            ->where('section_id', $sectionId)
            ->where('school_year_id', $schoolYearId)
            ->where('status', '!=', self::STATUS_CURSANDO)
            ->exists();
    }

    /**
     * Calcular la nota final de la materia con el MISMO cálculo que "Mis notas"
     * (NotaService): cada parcial se calcula con sus parámetros y porcentajes,
     * y la nota final es la suma de los parciales de todos los módulos.
     *
     * Antes se sumaban todos los puntos y se dividía entre los puntos posibles,
     * sin pesos: un estudiante podía ver 7/10 (aprueba) en "Mis notas" y quedar
     * reprobado con 66,67 % al cerrar el curso.
     *
     * Retorna null si el estudiante no tiene ninguna entrega calificada.
     *
     * @return array{total_obtained: float, total_possible: float, percentage: float, final_grade: float, parciales: array}|null
     */
    public function calculateFinalGrade(int $studentId, int $subjectId): ?array
    {
        $tieneCalificaciones = Entrega::where('estudiante_id', $studentId)
            ->whereNotNull('nota')
            ->whereHas('tarea.modulo', fn ($q) => $q->where('materia_id', $subjectId))
            ->exists();

        if (!$tieneCalificaciones) {
            return null;
        }

        $parciales = $this->parcialesEvaluables($subjectId);

        $notaService = app(NotaService::class);
        $totalObtained = 0.0;
        $totalPossible = 0.0;
        $detalleParciales = [];

        foreach ($parciales as $parcial) {
            $resultado = $notaService->calcularNotaParcial($studentId, $parcial);
            $nota = (float) ($resultado['nota_final'] ?? 0);
            $maxima = (float) ($parcial->nota_maxima ?? 100);

            $totalObtained += $nota;
            $totalPossible += $maxima;

            $detalleParciales[] = [
                'parcial_id' => $parcial->id,
                'parcial_nombre' => $parcial->nombre,
                // null = el parcial todavía no tiene calificaciones
                'nota' => empty($resultado['detalles']) ? null : round($nota, 2),
                'nota_maxima' => $maxima,
                'porcentaje_obtenido' => $maxima > 0 ? round(($nota / $maxima) * 100, 2) : 0,
            ];
        }

        if ($totalPossible <= 0) {
            return null;
        }

        $percentage = round(($totalObtained / $totalPossible) * 100, 2);

        return [
            'total_obtained' => round($totalObtained, 2),
            'total_possible' => round($totalPossible, 2),
            'percentage' => $percentage,
            'final_grade' => $percentage,
            'parciales' => $detalleParciales,
        ];
    }

    /**
     * Determinar estado basado en porcentaje de aprobación (70%)
     * 
     * @param array|null $gradeData Datos del calculateFinalGrade
     * @return string Estado del curso
     */
    public function determineStatus(?array $gradeData): string
    {
        // Si no hay datos de nota → concludeido
        if ($gradeData === null) {
            return self::STATUS_CONCLUIDO;
        }

        $percentage = $gradeData['percentage'];
        $passingPercentage = self::PASSING_PERCENTAGE; // 70%

        // Comparar contra el porcentaje mínimo
        if ($percentage >= $passingPercentage) {
            return self::STATUS_APROBADO;
        }
        
        return self::STATUS_REPROBADO;
    }

    /**
     * Determinar estadolegacy (para compatibilidad) - acepta float
     * @deprecated Usar determineStatus con array
     */
    public function determineStatusLegacy(?float $grade): string
    {
        if ($grade === null) {
            return self::STATUS_CONCLUIDO;
        }

        return $grade >= self::PASSING_PERCENTAGE
            ? self::STATUS_APROBADO
            : self::STATUS_REPROBADO;
    }

    /**
     * Parciales que cuentan para la nota de una materia: los de sus módulos
     * que tienen al menos una tarea. Un parcial sin tareas (ej.: el
     * "Parcial 2" creado por defecto y nunca usado) no cuenta; si contara,
     * restaría su nota máxima a todos los estudiantes.
     */
    public function parcialesEvaluables(int $subjectId)
    {
        return Parcial::with('parametros')
            ->whereHas('modulo', fn ($q) => $q->where('materia_id', $subjectId))
            ->whereExists(fn ($q) => $q->from('tareas')
                ->whereColumn('tareas.parcial_id', 'parciales.id')
                ->whereNull('tareas.deleted_at'))
            ->orderBy('modulo_id')
            ->orderBy('numero')
            ->get();
    }

    /**
     * Notas por parcial (mismo cálculo ponderado que "Mis notas")
     */
    public function calculateParcialGrades(int $studentId, int $subjectId): array
    {
        return $this->calculateFinalGrade($studentId, $subjectId)['parciales'] ?? [];
    }

    /**
     * Obtener notas de un estudiante para un parcial específico
     */
    private function getEstudianteNotas(int $studentId, int $parcialId): array
    {
        // Aquí implementas la lógica de cálculo de notas por parcial
        // Por ahora, retornamos la nota del parcial si existe
        
        $parcial = Parcial::with('parametros')->find($parcialId);
        
        if (!$parcial) {
            return ['nota_final' => null, 'porcentaje' => 0];
        }

        $sumaPonderada = 0;
        $totalPorcentaje = 0;

        foreach ($parcial->parametros as $parametro) {
            // Obtener entregas del estudiante para este parámetro
            $entregas = Entrega::where('estudiante_id', $studentId)
                ->whereHas('tarea', function ($query) use ($parametro) {
                    $query->where('parametro_id', $parametro->id);
                })
                ->whereNotNull('nota')
                ->get();

            if ($entregas->isNotEmpty()) {
                // Calcular promedio del parámetro
                $notaParametro = $entregas->avg('nota');
                
                // Ponderar según el porcentaje del parámetro
                $ponderacion = ($notaParametro / 100) * $parametro->porcentaje;
                $sumaPonderada += $ponderacion;
                $totalPorcentaje += $parametro->porcentaje;
            }
        }

        // Calcular nota final del parcial (en escala de nota_maxima)
        $notaFinal = $totalPorcentaje > 0 
            ? ($sumaPonderada / $totalPorcentaje) * ($parcial->nota_maxima ?? 100)
            : null;

        return [
            'nota_final' => $notaFinal ? round($notaFinal, 2) : null,
            'porcentaje' => $sumaPonderada
        ];
    }

    /**
     * Obtener profesor de la materia
     */
    public function getCourseProfessor(Subject $subject, Section $section): ?User
    {
        $schedule = ClassSchedule::where('subject_id', $subject->id)
            ->where('section_id', $section->id)
            ->first();

        return $schedule?->teacher;
    }

    /**
     * Obtener historial académico del estudiante
     */
    public function getStudentHistory(int $studentId, ?int $schoolYearId = null)
    {
        $query = StudentCourse::with(['subject', 'section', 'schoolYear', 'profesor', 'closedBy'])
            ->where('student_id', $studentId)
            ->orderBy('closed_at', 'desc');

        if ($schoolYearId) {
            $query->where('school_year_id', $schoolYearId);
        }

        return $query->get();
    }
}
