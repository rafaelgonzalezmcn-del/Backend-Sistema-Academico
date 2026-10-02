<?php

namespace App\Services;

use App\Models\Section;
use App\Models\StudentCourse;
use App\Models\Subject;
use App\Models\User;

/**
 * Arma los datos del acta de calificaciones de una materia en una sección.
 *
 * - Curso CERRADO: usa los registros oficiales guardados al cerrar (student_courses).
 * - Curso ABIERTO: calcula las notas en el momento con el mismo cálculo que
 *   "Mis notas" y el cierre de curso, y marca el acta como PROVISIONAL.
 *
 * Los datos son independientes del formato: el PDF y el Excel usan la misma estructura.
 */
class ActaService
{
    public function __construct(private CourseClosureService $cierre) {}

    public function generar(Subject $materia, Section $seccion): array
    {
        $seccion->loadMissing(['grade', 'schoolYear']);
        $anioLectivoId = $seccion->school_year_id;

        $registros = StudentCourse::with('student:id,first_name,last_name,identification_number')
            ->where('subject_id', $materia->id)
            ->where('section_id', $seccion->id)
            ->where('school_year_id', $anioLectivoId)
            ->where('status', '!=', CourseClosureService::STATUS_CURSANDO)
            ->get();

        $cerrado = $registros->isNotEmpty();

        [$parciales, $filas] = $cerrado
            ? $this->desdeCierre($registros)
            : $this->calculoProvisional($materia, $seccion);

        // Orden alfabético por apellido ignorando tildes (Álvarez va con la A)
        usort($filas, fn ($a, $b) => strcasecmp(
            \Illuminate\Support\Str::ascii($a['estudiante']),
            \Illuminate\Support\Str::ascii($b['estudiante'])
        ));
        foreach ($filas as $i => &$fila) {
            $fila['numero'] = $i + 1;
        }
        unset($fila);

        $profesor = $this->cierre->getCourseProfessor($materia, $seccion);

        return [
            'institucion' => config('app.institucion'),
            // APP_NAME viene como "Laravel" en una instalación nueva: no mostrarlo en un acta
            'sistema' => config('app.name') === 'Laravel' ? 'Sistema Académico' : config('app.name'),
            'materia' => $materia->name,
            'grado' => $seccion->grade?->name,
            'seccion' => $seccion->name,
            'anio_lectivo' => $seccion->schoolYear?->name,
            'profesor' => $profesor ? trim($profesor->first_name . ' ' . $profesor->last_name) : null,
            'cerrado' => $cerrado,
            'fecha_cierre' => $cerrado ? $registros->max('closed_at')?->format('d/m/Y') : null,
            'emitido' => now()->format('d/m/Y H:i'),
            'porcentaje_aprobacion' => CourseClosureService::PASSING_PERCENTAGE,
            'parciales' => $parciales,
            'nota_maxima_total' => array_sum(array_column($parciales, 'nota_maxima')),
            'filas' => $filas,
            'resumen' => $this->resumen($filas),
        ];
    }

    /**
     * Curso cerrado: notas oficiales guardadas en el historial.
     */
    private function desdeCierre($registros): array
    {
        // Todos los estudiantes del curso se cerraron con los mismos parciales
        $parciales = collect($registros->first()->parcial_grades ?? [])
            ->map(fn ($p) => [
                'id' => $p['parcial_id'] ?? null,
                'nombre' => $p['parcial_nombre'] ?? 'Parcial',
                'nota_maxima' => (float) ($p['nota_maxima'] ?? 0),
            ])->values()->all();

        $filas = $registros->map(function (StudentCourse $r) use ($parciales) {
            $notasPorParcial = collect($r->parcial_grades ?? [])->keyBy('parcial_id');

            return $this->fila(
                $r->student,
                array_map(fn ($p) => $notasPorParcial[$p['id']]['nota'] ?? null, $parciales),
                $r->total_score_obtained !== null ? (float) $r->total_score_obtained : null,
                $r->final_grade !== null ? (float) $r->final_grade : null,
                $r->status
            );
        })->all();

        return [$parciales, $filas];
    }

    /**
     * Curso abierto: cálculo en el momento (provisional).
     */
    private function calculoProvisional(Subject $materia, Section $seccion): array
    {
        $parciales = $this->cierre->parcialesEvaluables($materia->id)
            ->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'nota_maxima' => (float) ($p->nota_maxima ?? 100),
            ])->values()->all();

        $estudiantes = $this->cierre->getStudentsForCourse($materia, $seccion);

        $filas = $estudiantes->map(function (User $estudiante) use ($materia, $parciales) {
            $nota = $this->cierre->calculateFinalGrade($estudiante->id, $materia->id);
            $notasPorParcial = collect($nota['parciales'] ?? [])->keyBy('parcial_id');

            return $this->fila(
                $estudiante,
                array_map(fn ($p) => $notasPorParcial[$p['id']]['nota'] ?? null, $parciales),
                $nota['total_obtained'] ?? null,
                $nota['percentage'] ?? null,
                $this->cierre->determineStatus($nota)
            );
        })->all();

        return [$parciales, $filas];
    }

    private function fila(?User $estudiante, array $notasParciales, ?float $total, ?float $porcentaje, string $estado): array
    {
        return [
            'numero' => 0,
            'estudiante' => $estudiante
                ? trim(($estudiante->last_name ?? '') . ' ' . $estudiante->first_name)
                : 'Estudiante eliminado',
            'identificacion' => $estudiante?->identification_number,
            'notas_parciales' => $notasParciales,
            'total' => $total,
            'porcentaje' => $porcentaje,
            'estado' => $estado,
            'estado_texto' => match ($estado) {
                CourseClosureService::STATUS_APROBADO => 'Aprobado',
                CourseClosureService::STATUS_REPROBADO => 'Reprobado',
                CourseClosureService::STATUS_CONCLUIDO => 'Sin calificaciones',
                default => ucfirst($estado),
            },
        ];
    }

    private function resumen(array $filas): array
    {
        $conNota = array_filter($filas, fn ($f) => $f['porcentaje'] !== null);

        return [
            'total' => count($filas),
            'aprobados' => count(array_filter($filas, fn ($f) => $f['estado'] === CourseClosureService::STATUS_APROBADO)),
            'reprobados' => count(array_filter($filas, fn ($f) => $f['estado'] === CourseClosureService::STATUS_REPROBADO)),
            'sin_calificaciones' => count($filas) - count($conNota),
            'promedio_porcentaje' => $conNota
                ? round(array_sum(array_column($conNota, 'porcentaje')) / count($conNota), 2)
                : null,
        ];
    }
}
