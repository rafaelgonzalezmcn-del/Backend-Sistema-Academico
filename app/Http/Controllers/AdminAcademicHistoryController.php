<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Section;
use App\Models\StudentCourse;
use Illuminate\Http\Request;

class AdminAcademicHistoryController extends Controller
{
    /**
     * Historial académico completo de un estudiante.
     * Retorna todas las materias con estado, nota, año lectivo, sección, profesor y fecha de cierre.
     */
    public function studentHistory($studentId)
    {
        // Validar que el usuario existe y es estudiante
        $student = User::with(['role'])->find($studentId);

        if (!$student) {
            return response()->json(['message' => 'El estudiante no existe'], 404);
        }

        if (!$student->isStudent()) {
            return response()->json(['message' => 'El usuario no es un estudiante'], 400);
        }

        // Obtener historial con todas las relaciones (evitar N+1)
        $courses = StudentCourse::with([
            'subject:id,name',
            'section:id,name,grade_id',
            'section.grade:id,name',
            'schoolYear:id,name',
            'profesor:id,first_name,last_name',
        ])
        ->where('student_id', $studentId)
        ->orderBy('school_year_id', 'desc')
        ->orderBy('closed_at', 'desc')
        ->get();

        $history = $courses->map(function ($course) {
            // Extraer notas de parciales desde parcial_grades (array JSON)
            $parciales = $course->parcial_grades ?? [];
            $parcial1 = null;
            $parcial2 = null;

            if (is_array($parciales)) {
                foreach ($parciales as $p) {
                    $nombre = strtolower($p['parcial_nombre'] ?? '');
                    $nota = $p['nota'] ?? null;
                    if (str_contains($nombre, '1') || str_contains($nombre, 'primer') || str_contains($nombre, 'primero')) {
                        $parcial1 = $nota;
                    } elseif (str_contains($nombre, '2') || str_contains($nombre, 'segundo') || str_contains($nombre, 'segund')) {
                        $parcial2 = $nota;
                    }
                }
                // Fallback: si no se pudo identificar por nombre, tomar por posición
                if ($parcial1 === null && $parcial2 === null && count($parciales) >= 1) {
                    $parcial1 = $parciales[0]['nota'] ?? null;
                }
                if ($parcial2 === null && count($parciales) >= 2) {
                    $parcial2 = $parciales[1]['nota'] ?? null;
                }
            }

            return [
                'id' => $course->id,
                'subject' => $course->subject?->name ?? 'Materia eliminada',
                'status' => $course->status ?? 'cursando',
                'status_label' => $course->status_label ?? 'Cursando',
                'final_grade' => $course->final_grade,
                'parcial_1' => $parcial1,
                'parcial_2' => $parcial2,
                'school_year' => $course->schoolYear?->name ?? 'N/A',
                'section' => $course->section?->name ?? 'N/A',
                'grade' => $course->section?->grade?->name ?? 'N/A',
                'profesor' => $course->profesor
                    ? trim($course->profesor->first_name . ' ' . $course->profesor->last_name)
                    : 'N/A',
                'closed_at' => $course->closed_at?->format('Y-m-d'),
                'observations' => $course->observations,
            ];
        });

        return response()->json([
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'name' => trim($student->first_name . ' ' . $student->last_name),
                    'identification_number' => $student->identification_number,
                    'current_section' => $student->section?->name,
                    'current_grade' => $student->section?->grade?->name,
                ],
                'courses' => $history,
                'summary' => [
                    'total' => $courses->count(),
                    'approved' => $courses->where('status', 'aprobado')->count(),
                    'failed' => $courses->where('status', 'reprobado')->count(),
                    'pending' => $courses->where('status', 'cursando')->count(),
                    'concluded' => $courses->whereIn('status', ['concluido', 'retirado'])->count(),
                ],
            ],
        ]);
    }

    /**
     * Resumen académico de una sección completa.
     * Retorna conteo de aprobados/reprobados/pendientes y detalle por estudiante.
     */
    public function sectionSummary($sectionId)
    {
        $section = Section::with(['grade', 'schoolYear'])->find($sectionId);

        if (!$section) {
            return response()->json(['message' => 'La sección no existe'], 404);
        }

        // Obtener estudiantes activos de la sección
        $students = User::with(['role'])
            ->where('section_id', $sectionId)
            ->whereHas('role', fn($q) => $q->where('name', 'estudiante'))
            ->where('activo', true)
            ->get();

        $studentSummaries = [];
        $totalApproved = 0;
        $totalFailed = 0;
        $totalPending = 0;

        foreach ($students as $student) {
            $courses = StudentCourse::where('student_id', $student->id)->get();

            $approved = $courses->where('status', 'aprobado')->count();
            $failed = $courses->where('status', 'reprobado')->count();
            $pending = $courses->where('status', 'cursando')->count();

            $totalApproved += $approved;
            $totalFailed += $failed;
            $totalPending += $pending;

            $studentSummaries[] = [
                'id' => $student->id,
                'name' => trim($student->first_name . ' ' . $student->last_name),
                'identification_number' => $student->identification_number,
                'approved' => $approved,
                'failed' => $failed,
                'pending' => $pending,
                'total_courses' => $courses->count(),
                'approval_rate' => $courses->count() > 0
                    ? round(($approved / $courses->count()) * 100, 1)
                    : null,
            ];
        }

        return response()->json([
            'data' => [
                'section' => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'grade' => $section->grade?->name,
                    'school_year' => $section->schoolYear?->name,
                ],
                'total_students' => $students->count(),
                'approved' => $totalApproved,
                'failed' => $totalFailed,
                'pending' => $totalPending,
                'students' => $studentSummaries,
            ],
        ]);
    }
}
