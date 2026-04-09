<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Subject;
use App\Models\Section;
use App\Models\ClassSchedule;
use App\Models\StudentCourse;
use App\Models\SchoolYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TeacherDashboardController extends Controller
{
    /**
     * GET /api/teacher/dashboard
     * Dashboard general del profesor
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        
        // Si es admin, puede ver todo
        if ($user->isAdmin()) {
            return $this->getAdminDashboard();
        }
        
        // Si es profesor, solo sus cursos
        if ($user->isTeacher()) {
            return $this->getTeacherDashboard($user);
        }
        
        return response()->json([
            'message' => 'No autorizado'
        ], 403);
    }

    /**
     * GET /api/teacher/courses/stats
     * Estadísticas por curso (materia + sección)
     */
    public function courseStats(Request $request)
    {
        $user = $request->user();
        
        if ($user->isAdmin()) {
            return $this->getAdminCourseStats();
        }
        
        if ($user->isTeacher()) {
            return $this->getTeacherCourseStats($user);
        }
        
        return response()->json([
            'message' => 'No autorizado'
        ], 403);
    }

    /**
     * Dashboard para administradores
     */
    private function getAdminDashboard()
    {
        // Total de cursos (combinaciones únicas de materia + sección)
        $totalCourses = ClassSchedule::distinct('subject_id', 'section_id')->count('subject_id');
        
        // Total de estudiantes únicos
        $totalStudents = User::whereHas('role', fn($q) => $q->where('name', 'estudiante'))->count();
        
        // Cursos cerrados
        $closedCourses = StudentCourse::distinct('subject_id', 'section_id', 'school_year_id')
            ->whereIn('status', ['aprobado', 'reprobado', 'concluido', 'retirado'])
            ->count('subject_id');
        
        // Cursos abiertos (tienen estudiantes en secciones pero no están cerrados)
        $activeSections = Section::count();
        $openCourses = $activeSections - $closedCourses;
        
        return response()->json([
            'summary' => [
                'total_courses' => $totalCourses,
                'total_students' => $totalStudents,
                'closed_courses' => $closedCourses,
                'open_courses' => max(0, $openCourses)
            ]
        ]);
    }

    /**
     * Dashboard para profesores
     */
    private function getTeacherDashboard(User $teacher)
    {
        // Obtener los horarios del profesor
        $schedules = ClassSchedule::where('teacher_id', $teacher->id)
            ->with(['subject', 'section', 'section.schoolYear'])
            ->get();
        
        // Obtener combinaciones únicas de materia + sección
        $courseCombinations = $schedules->unique(fn($s) => $s->subject_id . '-' . $s->section_id);
        
        // Total de cursos asignados
        $totalCourses = $courseCombinations->count();
        
        // Estudiantes únicos en sus cursos
        $studentIds = [];
        foreach ($courseCombinations as $schedule) {
            $studentsInSection = $schedule->section->students()->pluck('users.id');
            $studentIds = array_merge($studentIds, $studentsInSection->toArray());
        }
        $totalStudents = count(array_unique($studentIds));
        
        // Cursos cerrados (donde existen registros en student_courses)
        $closedCourses = 0;
        $openCourses = 0;
        
        foreach ($courseCombinations as $schedule) {
            $section = $schedule->section;
            $schoolYearId = $section?->school_year_id;
            
            if (!$schoolYearId) continue;
            
            $hasClosedRecord = StudentCourse::where('subject_id', $schedule->subject_id)
                ->where('section_id', $section->id)
                ->where('school_year_id', $schoolYearId)
                ->whereIn('status', ['aprobado', 'reprobado', 'concluido', 'retirado'])
                ->exists();
            
            if ($hasClosedRecord) {
                $closedCourses++;
            } else {
                $openCourses++;
            }
        }
        
        return response()->json([
            'summary' => [
                'total_courses' => $totalCourses,
                'total_students' => $totalStudents,
                'closed_courses' => $closedCourses,
                'open_courses' => $openCourses
            ]
        ]);
    }

    /**
     * Estadísticas de cursos para administradores
     */
    private function getAdminCourseStats()
    {
        // Obtener todas las combinaciones de materia + sección
        $schedules = ClassSchedule::with([
            'subject',
            'section',
            'section.schoolYear'
        ])->get();
        
        $courses = $schedules->unique(fn($s) => $s->subject_id . '-' . $s->section_id)->values();
        
        $stats = [];
        
        foreach ($courses as $schedule) {
            $section = $schedule->section;
            $schoolYearId = $section?->school_year_id;
            
            if (!$section || !$schoolYearId) continue;
            
            $stats[] = $this->calculateCourseStats(
                $schedule->subject_id,
                $section->id,
                $schoolYearId,
                $schedule->subject?->name,
                $section->name,
                $section->schoolYear?->name
            );
        }
        
        return response()->json([
            'courses' => $stats
        ]);
    }

    /**
     * Estadísticas de cursos para profesores
     */
    private function getTeacherCourseStats(User $teacher)
    {
        // Solo los cursos donde el profesor es teacher
        $schedules = ClassSchedule::where('teacher_id', $teacher->id)
            ->with([
                'subject',
                'section',
                'section.schoolYear'
            ])
            ->get();
        
        $courses = $schedules->unique(fn($s) => $s->subject_id . '-' . $s->section_id)->values();
        
        $stats = [];
        
        foreach ($courses as $schedule) {
            $section = $schedule->section;
            $schoolYearId = $section?->school_year_id;
            
            if (!$section || !$schoolYearId) continue;
            
            // Verificar que el profesor es el teacher de esta materia en esta sección
            $isTeacherOfCourse = ClassSchedule::where('subject_id', $schedule->subject_id)
                ->where('section_id', $section->id)
                ->where('teacher_id', $teacher->id)
                ->exists();
            
            if (!$isTeacherOfCourse) continue;
            
            $stats[] = $this->calculateCourseStats(
                $schedule->subject_id,
                $section->id,
                $schoolYearId,
                $schedule->subject?->name,
                $section->name,
                $section->schoolYear?->name
            );
        }
        
        return response()->json([
            'courses' => $stats
        ]);
    }

    /**
     * Calcular estadísticas de un curso específico
     */
    private function calculateCourseStats(
        int $subjectId,
        int $sectionId,
        int $schoolYearId,
        ?string $subjectName,
        ?string $sectionName,
        ?string $schoolYearName
    ): array {
        // Estudiantes en esta sección
        $studentsInSection = User::where('section_id', $sectionId)
            ->whereHas('role', fn($q) => $q->where('name', 'estudiante'))
            ->count();
        
        // Registros en student_courses para esta materia+sección+año
        $courseRecords = StudentCourse::where('subject_id', $subjectId)
            ->where('section_id', $sectionId)
            ->where('school_year_id', $schoolYearId)
            ->get();
        
        // Determinar estado del curso
        $hasClosedRecords = $courseRecords->whereIn('status', ['aprobado', 'reprobado', 'concluido', 'retirado'])->isNotEmpty();
        $status = $hasClosedRecords ? 'cerrado' : 'abierto';
        
        // Calcular estadísticas solo si hay registros
        $averageGrade = null;
        $approvedCount = 0;
        $failedCount = 0;
        $totalWithGrades = 0;
        
        if ($courseRecords->isNotEmpty()) {
            $grades = $courseRecords->whereNotNull('final_grade')->pluck('final_grade');
            
            if ($grades->isNotEmpty()) {
                $averageGrade = round($grades->avg(), 2);
                $totalWithGrades = $grades->count();
            }
            
            $approvedCount = $courseRecords->where('status', 'aprobado')->count();
            $failedCount = $courseRecords->whereIn('status', ['reprobado', 'retirado'])->count();
        }
        
        // Porcentajes
        $approvedPercentage = $totalWithGrades > 0 
            ? round(($approvedCount / $totalWithGrades) * 100, 1) 
            : 0;
        $failedPercentage = $totalWithGrades > 0 
            ? round(($failedCount / $totalWithGrades) * 100, 1) 
            : 0;
        
        return [
            'subject_id' => $subjectId,
            'subject_name' => $subjectName,
            'section_id' => $sectionId,
            'section_name' => $sectionName,
            'school_year' => $schoolYearName,
            'total_students' => $studentsInSection,
            'registered_students' => $courseRecords->count(),
            'average_grade' => $averageGrade,
            'approved_count' => $approvedCount,
            'failed_count' => $failedCount,
            'approved_percentage' => $approvedPercentage,
            'failed_percentage' => $failedPercentage,
            'status' => $status
        ];
    }
}
