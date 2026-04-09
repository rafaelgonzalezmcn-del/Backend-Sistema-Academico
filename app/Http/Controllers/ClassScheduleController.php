<?php

namespace App\Http\Controllers;

use App\Models\ClassSchedule;
use App\Http\Requests\StoreClassScheduleRequest;
use App\Http\Requests\UpdateClassScheduleRequest;
use App\Http\Resources\ClassScheduleResource;
use App\Services\ClassScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ClassScheduleController extends Controller
{
    public function __construct(
        private ClassScheduleService $scheduleService
    ) {}

    /**
     * GET /class-schedules
     * Listar todos los horarios
     */
    public function index(Request $request)
    {
        // Para count_only, permitir cualquier usuario autenticado
        if ($request->has('count_only')) {
            $query = ClassSchedule::query();
            $user = Auth::user();
            
            // Si es profesor, solo contar sus horarios
            if ($user->role && $user->role->name === 'profesor') {
                $query->where('teacher_id', $user->id);
            }
            
            return response()->json([
                'total' => $query->count()
            ]);
        }

        // Para lista completa, requerir rol específico
        $this->authorize('viewAny', ClassSchedule::class);
        
        $query = ClassSchedule::with([
            'teacher:id,first_name,last_name',
            'subject:id,name',
            'section.grade',
            'section.schoolYear'
        ]);

        $schedules = $query->paginate(15);
        
        return response()->json([
            'data' => ClassScheduleResource::collection($schedules)->resolve(),
            'meta' => [
                'current_page' => $schedules->currentPage(),
                'last_page' => $schedules->lastPage(),
                'total' => $schedules->total(),
                'per_page' => $schedules->perPage(),
            ],
        ]);
    }

    /**
     * GET /class-schedules/count
     * Contar horarios (accesible para admin y profesor)
     */
    public function count(Request $request)
    {
        $query = ClassSchedule::query();

        // Si es profesor, solo mostrar sus horarios
        $user = Auth::user();
        if ($user->role && $user->role->name === 'profesor') {
            $query->where('teacher_id', $user->id);
        }

        return response()->json([
            'total' => $query->count()
        ]);
    }

    /**
     * POST /class-schedules
     * Crear horario (solo admin)
     */
    public function store(StoreClassScheduleRequest $request)
    {
        try {
            $this->authorize('create', ClassSchedule::class);
            
            $schedule = $this->scheduleService->create($request->validated());

            return response()->json([
                'data' => new ClassScheduleResource($schedule),
                'message' => 'Horario creado correctamente'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error guardando horario: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /class-schedules/{classSchedule}
     * Ver horario específico
     */
    public function show(ClassSchedule $classSchedule)
    {
        $this->authorize('view', $classSchedule);
        
        return response()->json([
            'data' => new ClassScheduleResource($classSchedule->load(['teacher', 'subject', 'section', 'schoolYear']))
        ]);
    }

    /**
     * PUT /class-schedules/{classSchedule}
     * Actualizar horario (solo admin)
     */
    public function update(UpdateClassScheduleRequest $request, ClassSchedule $classSchedule)
    {
        try {
            $this->authorize('update', $classSchedule);
            
            $schedule = $this->scheduleService->update($classSchedule, $request->validated());

            return response()->json([
                'data' => new ClassScheduleResource($schedule),
                'message' => 'Horario actualizado correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error actualizando horario: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * DELETE /class-schedules/{classSchedule}
     * Eliminar horario (solo admin)
     */
    public function destroy(ClassSchedule $classSchedule)
    {
        try {
            $this->authorize('delete', $classSchedule);
            
            $this->scheduleService->delete($classSchedule);

            return response()->json(['message' => 'Horario eliminado correctamente']);
        } catch (\Exception $e) {
            Log::error('Error eliminando horario: ' . $e->getMessage());
            return response()->json(['message' => 'Error al eliminar el horario'], 500);
        }
    }

    /**
     * GET /sections/{id}/schedule
     * Horario de una sección
     */
    public function sectionSchedule($id)
    {
        $schoolYearId = request()->query('school_year_id');
        $result = $this->scheduleService->getSectionSchedule($id, $schoolYearId);
        
        return response()->json($result);
    }

    /**
     * GET /teachers/{id}/schedule
     * Horario de un profesor
     */
    public function teacherSchedule($id)
    {
        $schoolYearId = request()->query('school_year_id');
        $result = $this->scheduleService->getTeacherSchedule($id, $schoolYearId);
        
        return response()->json($result);
    }

    /**
     * GET /students/{sectionId}/schedule
     * Horario de estudiante (vía sección)
     */
    public function studentSchedule($sectionId)
    {
        return $this->sectionSchedule($sectionId);
    }

    /**
     * GET /my-schedule
     * Mi horario según rol
     */
    public function mySchedule()
    {
        try {
            $user = Auth::user();

            if (!$user->role) {
                return response()->json([
                    'message' => 'El usuario no tiene un rol asignado'
                ], 403);
            }

            $schoolYearId = request()->query('school_year_id');
            $result = $this->scheduleService->getMySchedule($user, $schoolYearId);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error consultando my-schedule: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
