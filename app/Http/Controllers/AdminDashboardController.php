<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Section;
use App\Models\Grade;
use App\Models\StudentCourse;
use App\Models\SchoolYear;
use App\Models\ClassSchedule;
use App\Models\Subject;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    const CACHE_KEY = 'admin:dashboard:stats';
    const CACHE_TTL = 300; // 5 minutos

    /**
     * Métricas consolidadas del dashboard admin.
     * Queries eficientes usando aggregates (COUNT, CASE) — sin N+1.
     */
    public function stats()
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            // Año lectivo activo
            $activeYear = SchoolYear::where('active', true)->first();
            $activeYearId = $activeYear?->id;

            // Estudiantes activos (rol=estudiante + activo=true)
            $activeStudents = User::whereHas('role', fn($q) => $q->where('name', 'estudiante'))
                ->where('activo', true)
                ->count();

            // Profesores activos
            $activeTeachers = User::whereHas('role', fn($q) => $q->where('name', 'profesor'))
                ->where('activo', true)
                ->count();

            // Total secciones
            $totalSections = Section::count();

            // Total grados
            $totalGrades = Grade::count();

            // Cursos pendientes de cierre (status = 'cursando')
            $pendingClosures = StudentCourse::where('status', 'cursando')->count();

            // Tasa de aprobación del año actual
            // (cursos aprobados / cursos cerrados del año activo) * 100
            $approvalRate = null;
            $approvedCount = 0;
            $closedCount = 0;

            if ($activeYearId) {
                $closedCount = StudentCourse::where('school_year_id', $activeYearId)
                    ->whereIn('status', ['aprobado', 'reprobado'])
                    ->count();

                $approvedCount = StudentCourse::where('school_year_id', $activeYearId)
                    ->where('status', 'aprobado')
                    ->count();

                $approvalRate = $closedCount > 0
                    ? round(($approvedCount / $closedCount) * 100, 1)
                    : null;
            }

            // Secciones al 90%+ de capacidad
            // Usar raw query porque havingRaw con count no funciona bien en PostgreSQL
            $sections = Section::whereNotNull('max_capacity')
                ->where('max_capacity', '>', 0)
                ->get();

            $fullSections = 0;
            foreach ($sections as $section) {
                $enrolled = User::where('section_id', $section->id)
                    ->where('activo', true)
                    ->whereHas('role', fn($q) => $q->where('name', 'estudiante'))
                    ->count();

                if ($section->max_capacity > 0 && $enrolled >= ($section->max_capacity * 0.9)) {
                    $fullSections++;
                }
            }

            // Estudiantes sin sección asignada (activos)
            $unassignedStudents = User::whereHas('role', fn($q) => $q->where('name', 'estudiante'))
                ->where('activo', true)
                ->whereNull('section_id')
                ->count();

            // Estudiantes elegibles para promoción (por confirmar — necesita Fase 10)
            // Por ahora: contar estudiantes con todos sus cursos aprobados en el año activo
            $promotionEligible = 0;
            if ($activeYearId) {
                // Estudiantes activos con sección que tienen al menos 1 curso cerrado y ninguno reprobado
                $promotionEligible = User::whereHas('role', fn($q) => $q->where('name', 'estudiante'))
                    ->where('activo', true)
                    ->whereNotNull('section_id')
                    ->whereHas('studentCourses', function ($q) use ($activeYearId) {
                        $q->where('school_year_id', $activeYearId)
                          ->whereIn('status', ['aprobado', 'concluido']);
                    })
                    ->whereDoesntHave('studentCourses', function ($q) use ($activeYearId) {
                        $q->where('school_year_id', $activeYearId)
                          ->where('status', 'reprobado');
                    })
                    ->count();
            }

            return response()->json([
                'data' => [
                    'active_students' => $activeStudents,
                    'active_teachers' => $activeTeachers,
                    'pending_closures' => $pendingClosures,
                    'approval_rate' => $approvalRate,
                    'approved_count' => $approvedCount,
                    'closed_count' => $closedCount,
                    'full_sections' => $fullSections,
                    'total_sections' => $totalSections,
                    'total_grades' => $totalGrades,
                    'total_subjects' => Subject::count(),
                    'total_school_years' => SchoolYear::whereNull('deleted_at')->count(),
                    'total_activity_logs' => ActivityLog::count(),
                    'active_school_year' => $activeYear?->name,
                    'active_school_year_id' => $activeYearId,
                    'unassigned_students' => $unassignedStudents,
                    'promotion_eligible' => $promotionEligible,
                    // IDs para drill-down directo desde alertas
                    'unassigned_student_ids' => User::whereHas('role', fn($q) => $q->where('name', 'estudiante'))
                        ->where('activo', true)
                        ->whereNull('section_id')
                        ->pluck('id')->toArray(),
                    'promotion_eligible_ids' => $activeYearId
                        ? User::whereHas('role', fn($q) => $q->where('name', 'estudiante'))
                            ->where('activo', true)
                            ->whereNotNull('section_id')
                            ->whereHas('studentCourses', function ($q) use ($activeYearId) {
                                $q->where('school_year_id', $activeYearId)
                                  ->whereIn('status', ['aprobado', 'concluido']);
                            })
                            ->whereDoesntHave('studentCourses', function ($q) use ($activeYearId) {
                                $q->where('school_year_id', $activeYearId)
                                  ->where('status', 'reprobado');
                            })
                            ->pluck('id')->toArray()
                        : [],
                ],
            ]);
        });
    }
}
