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
                $parcialGrades = $this->calculateParcialGrades($student->id, $subject->id);
                
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
        $parcialGrades = $this->calculateParcialGrades($student->id, $subject->id);
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
     * Calcular nota final usando SUMA TOTAL + PORCENTAJE (70%)
     * 
     * Retorna:
     * - total_obtained: suma de todas las notas obtenidas
     * - total_possible: suma de todos los puntajes máximos
     * - percentage: porcentaje obtenido
     */
    public function calculateFinalGrade(int $studentId, int $subjectId): ?array
    {
        // Obtener entregas del estudiante para esta materia
        $entregas = Entrega::where('estudiante_id', $studentId)
            ->whereHas('tarea', function ($query) use ($subjectId) {
                $query->whereHas('modulo', function ($q) use ($subjectId) {
                    $q->where('materia_id', $subjectId);
                });
            })
            ->whereNotNull('nota')
            ->with('tarea') // Cargar la relación tarea para obtener puntaje máximo
            ->get();

        if ($entregas->isEmpty()) {
            return null;
        }

        $totalObtained = 0;
        $totalPossible = 0;

        foreach ($entregas as $entrega) {
            // Nota obtenida por el estudiante
            $totalObtained += $entrega->nota;
            
            // Puntaje máximo de la tarea (si existe, si no usar 10 por defecto)
            $maxScore = $entrega->tarea?->puntaje_maximo ?? 10;
            $totalPossible += $maxScore;
        }

        // Evitar división por cero
        if ($totalPossible === 0) {
            return null;
        }

        $percentage = round(($totalObtained / $totalPossible) * 100, 2);

        return [
            'total_obtained' => round($totalObtained, 2),
            'total_possible' => round($totalPossible, 2),
            'percentage' => $percentage,
            'final_grade' => $percentage // Usamos el porcentaje como nota final para compatibilidad
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
     * Calcular notas por parcial
     * Devuelve array: [{parcial_id, parcial_nombre, nota, nota_maxima}, ...]
     * Ahora usa SUMA en lugar de promedio ponderado
     */
    public function calculateParcialGrades(int $studentId, int $subjectId): array
    {
        // Obtener parciales de la materia
        $parciales = Parcial::whereHas('modulo', function ($query) use ($subjectId) {
            $query->where('materia_id', $subjectId);
        })->orderBy('numero')->get();

        $parcialGrades = [];

        foreach ($parciales as $parcial) {
            // Obtener entregas del estudiante para este parcial
            $entregas = Entrega::where('estudiante_id', $studentId)
                ->whereHas('tarea', function ($query) use ($parcial) {
                    $query->where('parcial_id', $parcial->id);
                })
                ->whereNotNull('nota')
                ->with('tarea')
                ->get();

            $notaObtenida = 0;
            $notaMaxima = 0;

            foreach ($entregas as $entrega) {
                $notaObtenida += $entrega->nota;
                $notaMaxima += $entrega->tarea?->puntaje_maximo ?? 10;
            }

            $parcialGrades[] = [
                'parcial_id' => $parcial->id,
                'parcial_nombre' => $parcial->nombre,
                'nota' => $notaObtenida > 0 ? $notaObtenida : null,
                'nota_maxima' => $notaMaxima,
                'porcentaje_obtenido' => $notaMaxima > 0 ? round(($notaObtenida / $notaMaxima) * 100, 2) : 0
            ];
        }

        return $parcialGrades;
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
