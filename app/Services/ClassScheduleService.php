<?php

namespace App\Services;

use App\Models\ClassSchedule;
use App\Models\User;
use App\Models\Section;
use App\Models\SchoolYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\LogsActivity;

class ClassScheduleService
{
    use LogsActivity;
    
    /**
     * Días válidos de la semana (desde config/schedules.php)
     */
    private function getValidDays(): array
    {
        return config('schedules.valid_days', ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo']);
    }

    /**
     * Crear horario - school_year_id se deriva automáticamente desde section
     * IMPORTANTE: No guardar school_year_id en el modelo (columna eliminada)
     */
    public function create(array $data): ClassSchedule
    {
        // OBTENER SECCIÓN Y DERIVAR AÑO LECTIVO
        $section = Section::with('schoolYear')->find($data['section_id']);
        
        if (!$section) {
            throw new \Exception('La sección especificada no existe');
        }

        if (!$section->school_year_id) {
            throw new \Exception('La sección no tiene un año lectivo asignado');
        }

        // Verificar que el teacher tenga rol de profesor
        $teacher = User::with('role')->find($data['teacher_id']);
        if (!$teacher || !$teacher->isTeacher()) {
            throw new \Exception('El usuario seleccionado no es un profesor válido');
        }

        // Validar que start_time < end_time
        if ($data['start_time'] >= $data['end_time']) {
            throw new \Exception('La hora de inicio debe ser menor que la hora de fin');
        }

        // Derivar school_year_id para VALIDACIONES (no se guarda en BD)
        $validationData = $data;
        $validationData['school_year_id'] = $section->school_year_id;

        // Verificar conflicto de profesor
        $this->checkTeacherConflict($validationData);

        // Verificar conflicto de sección
        $this->checkSectionConflict($validationData);

        // NO incluir school_year_id en los datos a guardar (columna eliminada)
        unset($data['school_year_id']);

        // Crear el horario con transacción
        $schedule = DB::transaction(function () use ($data) {
            return ClassSchedule::create($data);
        });

        $this->logActivity($this->logEvent('class_schedule', 'created'), $schedule, null, true);

        return $schedule->load(['teacher', 'subject', 'section']);
    }

    /**
     * Actualizar horario
     */
    public function update(ClassSchedule $schedule, array $data): ClassSchedule
    {
        // Validar times si ambos están presentes
        if (isset($data['start_time']) && isset($data['end_time'])) {
            if ($data['start_time'] >= $data['end_time']) {
                throw new \Exception('La hora de inicio debe ser menor que la hora de fin');
            }
        }

        // Verificar que el teacher tenga rol de profesor si se cambia
        if (isset($data['teacher_id'])) {
            $teacher = User::with('role')->find($data['teacher_id']);
            if (!$teacher || !$teacher->isTeacher()) {
                throw new \Exception('El usuario seleccionado no es un profesor válido');
            }
        }

        // Si cambia section_id, obtener el nuevo school_year_id
        $newSectionId = $data['section_id'] ?? $schedule->section_id;
        $section = Section::with('schoolYear')->find($newSectionId);
        
        if (!$section) {
            throw new \Exception('La sección especificada no existe');
        }

        if (!$section->school_year_id) {
            throw new \Exception('La sección no tiene un año lectivo asignado');
        }

        // Obtener datos reales para verificar conflictos
        // school_year_id se deriva desde la sección, no desde el request
        $conflictData = [
            'teacher_id' => $data['teacher_id'] ?? $schedule->teacher_id,
            'subject_id' => $data['subject_id'] ?? $schedule->subject_id,
            'section_id' => $newSectionId,
            'school_year_id' => $section->school_year_id, // DERIVADO desde section
            'day' => $data['day'] ?? $schedule->day,
            'start_time' => $data['start_time'] ?? $schedule->start_time,
            'end_time' => $data['end_time'] ?? $schedule->end_time,
        ];

        // Verificar conflictos
        $this->checkTeacherConflict($conflictData, $schedule->id);
        $this->checkSectionConflict($conflictData, $schedule->id);

        $oldData = $schedule->toArray();
        
        // No incluir school_year_id en el update - se mantiene derivado
        unset($data['school_year_id']);
        
        $schedule->update($data);

        $changes = $this->getChanges($oldData, $data);
        $this->logActivity($this->logEvent('class_schedule', 'updated'), $schedule, $changes);

        return $schedule->load(['teacher', 'subject', 'section']);
    }

    /**
     * Eliminar horario
     */
    public function delete(ClassSchedule $schedule): void
    {
        $schedule->delete();

        $this->logActivity($this->logEvent('class_schedule', 'deleted'), $schedule, null, true);
    }

    /**
     * Obtener horario de sección organizado por días
     * Si no se especifica schoolYearId, usa el año activo
     * 
     * @return array{data: array, meta: array}
     */
    public function getSectionSchedule(int $sectionId, ?int $schoolYearId = null): array
    {
        // Si no se especifica año, derivar desde la sección
        if (!$schoolYearId) {
            $section = Section::find($sectionId);
            $schoolYearId = $section?->school_year_id;
        }

        $query = ClassSchedule::with([
            'teacher:id,first_name,last_name',
            'subject:id,name'
        ])
        ->where('section_id', $sectionId);

        if ($schoolYearId) {
            // school_year_id se deriva desde section (columna eliminada de class_schedules)
            $query->whereHas('section', function ($q) use ($schoolYearId) {
                $q->where('school_year_id', $schoolYearId);
            });
        }

        $schedules = $query->orderBy('start_time')->get();
        $organizedData = $this->organizeByDays($schedules);

        return [
            'data' => $organizedData,
            'meta' => [
                'section_id' => $sectionId,
                'school_year_id' => $schoolYearId
            ]
        ];
    }

    /**
     * Obtener horario de profesor
     * Si no se especifica schoolYearId, usa el año activo
     * 
     * @return array{data: array, meta: array}
     */
    public function getTeacherSchedule(int $teacherId, ?int $schoolYearId = null): array
    {
        // Si no se especifica año, usar el año activo
        if (!$schoolYearId) {
            $activeYear = SchoolYear::where('active', true)->first();
            $schoolYearId = $activeYear?->id;
        }

        $query = ClassSchedule::with([
            'subject:id,name',
            'section:id,name'
        ])
        ->where('teacher_id', $teacherId);

        if ($schoolYearId) {
            // school_year_id se deriva desde section (columna eliminada de class_schedules)
            $query->whereHas('section', function ($q) use ($schoolYearId) {
                $q->where('school_year_id', $schoolYearId);
            });
        }

        $schedules = $query->orderBy('day')->orderBy('start_time')->get();

        return [
            'data' => $schedules,
            'meta' => [
                'teacher_id' => $teacherId,
                'school_year_id' => $schoolYearId
            ]
        ];
    }

    /**
     * Obtener mi horario según rol
     */
    public function getMySchedule(User $user, ?int $schoolYearId = null): array
    {
        $roleName = $user->role?->name;

        // Obtener año lectivo activo si no se especifica
        if (!$schoolYearId) {
            $activeYear = SchoolYear::where('active', true)->first();
            $schoolYearId = $activeYear?->id;
        }

        if ($roleName === 'profesor') {
            return $this->getTeacherScheduleFormat($user->id, $schoolYearId);
        }

        if ($roleName === 'estudiante') {
            if (!$user->section_id) {
                throw new \Exception('El estudiante no tiene sección asignada');
            }
            return $this->getStudentScheduleFormat($user->section_id, $schoolYearId);
        }

        if ($roleName === 'admin') {
            return [
                'data' => null,
                'meta' => [
                    'role' => 'admin',
                    'message' => 'Los administradores no tienen horario asignado'
                ]
            ];
        }

        throw new \Exception('Rol no reconocido');
    }

    /**
     * Verificar conflicto de profesor
     * school_year_id se deriva desde section en $data
     * Usa overlapsWith() del modelo para la comparación de tiempos.
     */
    private function checkTeacherConflict(array $data, ?int $excludeId = null): void
    {
        // Si no viene school_year_id en data, derivar desde section
        if (!isset($data['school_year_id']) || !$data['school_year_id']) {
            $section = Section::find($data['section_id']);
            $data['school_year_id'] = $section?->school_year_id;
        }

        if (!$data['school_year_id']) {
            return; // No se puede verificar sin año lectivo
        }

        // school_year_id se deriva desde section (columna eliminada de class_schedules)
        $query = ClassSchedule::where('teacher_id', $data['teacher_id'])
            ->where('day', $data['day'])
            ->whereHas('section', function ($q) use ($data) {
                $q->where('school_year_id', $data['school_year_id']);
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $conflictingSchedules = $query->with(['subject', 'section.grade'])->get();

        // Usar overlapsWith() del modelo para verificar cada horario existente
        $candidate = new ClassSchedule([
            'day' => $data['day'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
        ]);

        foreach ($conflictingSchedules as $existing) {
            if ($candidate->overlapsWith($existing)) {
                $subjectName = $existing->subject?->name ?? 'Materia desconocida';
                $sectionName = $existing->section?->name ?? 'Sección desconocida';
                $gradeName = $existing->section?->grade?->name ?? '';
                $startTime = $this->formatTimeForDisplay($existing->start_time);
                $endTime = $this->formatTimeForDisplay($existing->end_time);
                $location = $gradeName ? "{$gradeName} - {$sectionName}" : $sectionName;

                Log::warning('Conflicto horario: profesor', [
                    'teacher_id' => $data['teacher_id'],
                    'day' => $data['day'],
                    'start_time' => $data['start_time'],
                    'conflict_with' => $existing->id,
                ]);

                throw new \Exception(
                    "El profesor ya tiene una clase en ese horario: {$subjectName} - {$location} ({$startTime}-{$endTime})"
                );
            }
        }
    }

    /**
     * Verificar conflicto de sección
     * school_year_id se deriva desde section en $data
     * Usa overlapsWith() del modelo para la comparación de tiempos.
     */
    private function checkSectionConflict(array $data, ?int $excludeId = null): void
    {
        // Si no viene school_year_id en data, derivar desde section
        if (!isset($data['school_year_id']) || !$data['school_year_id']) {
            $section = Section::find($data['section_id']);
            $data['school_year_id'] = $section?->school_year_id;
        }

        if (!$data['school_year_id']) {
            return; // No se puede verificar sin año lectivo
        }

        // school_year_id se deriva desde section (columna eliminada de class_schedules)
        $query = ClassSchedule::where('section_id', $data['section_id'])
            ->where('day', $data['day'])
            ->whereHas('section', function ($q) use ($data) {
                $q->where('school_year_id', $data['school_year_id']);
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $conflictingSchedules = $query->with(['subject', 'teacher'])->get();

        // Usar overlapsWith() del modelo para verificar cada horario existente
        $candidate = new ClassSchedule([
            'day' => $data['day'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
        ]);

        foreach ($conflictingSchedules as $existing) {
            if ($candidate->overlapsWith($existing)) {
                $subjectName = $existing->subject?->name ?? 'Materia desconocida';
                $startTime = $this->formatTimeForDisplay($existing->start_time);
                $endTime = $this->formatTimeForDisplay($existing->end_time);

                Log::warning('Conflicto horario: sección', [
                    'section_id' => $data['section_id'],
                    'day' => $data['day'],
                    'start_time' => $data['start_time'],
                    'conflict_with' => $existing->id,
                ]);

                throw new \Exception(
                    "La sección ya tiene una clase en ese horario: {$subjectName} ({$startTime}-{$endTime})"
                );
            }
        }
    }

    /**
     * Formatea un tiempo para mostrar en mensajes de error.
     * Acepta Carbon, string o null.
     */
    private function formatTimeForDisplay($time): string
    {
        if ($time instanceof \Carbon\Carbon) {
            return $time->format('H:i');
        }
        return (string) $time;
    }

    /**
     * Organizar horarios por días
     */
    private function organizeByDays($schedules): array
    {
        $days = $this->getValidDays();
        $result = [];

        foreach ($days as $day) {
            $result[$day] = $schedules
                ->where('day', $day)
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        // Hora local "HH:mm" (igual que en el resto de horarios).
                        // Antes se enviaba la fecha completa en UTC y el estudiante
                        // veía sus clases 5 horas más tarde.
                        'start_time' => $item->start_time?->format('H:i'),
                        'end_time' => $item->end_time?->format('H:i'),
                        'subject' => $item->subject->name,
                        'teacher' => $item->teacher->first_name . ' ' . $item->teacher->last_name
                    ];
                })->values();
        }

        return $result;
    }

    /**
     * Obtener formato de horario de profesor
     */
    private function getTeacherScheduleFormat(int $teacherId, ?int $schoolYearId): array
    {
        // Si no se especifica año, usar el activo
        if (!$schoolYearId) {
            $activeYear = SchoolYear::where('active', true)->first();
            $schoolYearId = $activeYear?->id;
        }

        $query = ClassSchedule::with([
            'subject:id,name',
            'section:id,name,grade_id'
        ])
        ->where('teacher_id', $teacherId);

        if ($schoolYearId) {
            // school_year_id se deriva desde section (columna eliminada de class_schedules)
            $query->whereHas('section', function ($q) use ($schoolYearId) {
                $q->where('school_year_id', $schoolYearId);
            });
        }

        $schedule = $query->orderBy('day')->orderBy('start_time')->get();

        return [
            'data' => $schedule,
            'meta' => [
                'role' => 'teacher',
                'school_year_id' => $schoolYearId
            ]
        ];
    }

    /**
     * Obtener formato de horario de estudiante
     */
    private function getStudentScheduleFormat(int $sectionId, ?int $schoolYearId): array
    {
        // Si no se especifica año, derivar desde la sección
        if (!$schoolYearId) {
            $section = Section::find($sectionId);
            $schoolYearId = $section?->school_year_id;
        }

        $query = ClassSchedule::with([
            'teacher:id,first_name,last_name',
            'subject:id,name'
        ])
        ->where('section_id', $sectionId);

        if ($schoolYearId) {
            // school_year_id se deriva desde section (columna eliminada de class_schedules)
            $query->whereHas('section', function ($q) use ($schoolYearId) {
                $q->where('school_year_id', $schoolYearId);
            });
        }

        $schedules = $query->orderBy('start_time')->get();

        return [
            'data' => $this->organizeByDays($schedules),
            'meta' => [
                // F3-T1: Estandarizado a 'estudiante' (antes 'student')
                'role' => 'estudiante',
                'section_id' => $sectionId,
                'school_year_id' => $schoolYearId
            ]
        ];
    }
}
