<?php

namespace App\Http\Controllers;

use App\Models\Modulo;
use App\Models\Material;
use App\Models\Subject;
use App\Http\Requests\StoreModuloRequest;
use App\Http\Requests\UpdateModuloRequest;
use App\Http\Requests\StoreMaterialRequest;
use App\Http\Resources\ModuloResource;
use App\Http\Resources\MaterialResource;
use App\Services\ModuloService;
use App\Services\MaterialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ModuloController extends Controller
{
    public function __construct(
        private ModuloService $moduloService,
        private MaterialService $materialService
    ) {}

    /**
     * Obtener módulos de una materia
     * GET /materias/{materiaId}/modulos
     */
    public function index(int $materiaId)
    {
        try {
            // Verificar que la materia existe
            $materia = Subject::findOrFail($materiaId);
            
            // La autorización se maneja por route middleware (auth:sanctum + active)
            // + verificación adicional de acceso a la materia
            $user = request()->user();
            if (!$this->moduloService->canAccessMateria($user, $materiaId)) {
                return response()->json(['message' => 'No tienes acceso a esta materia'], 403);
            }

            $result = $this->moduloService->getByMateria($materiaId);

            // Obtener las secciones donde el profesor enseña esta materia
            $user = request()->user();
            $sections = [];
            if ($user && $user->isTeacher()) {
                $schedules = \App\Models\ClassSchedule::where('subject_id', $materiaId)
                    ->where('teacher_id', $user->id)
                    ->with(['section:id,name,grade_id', 'section.grade:id,name'])
                    ->get();
                
                $sections = $schedules->map(fn($s) => [
                    'id' => $s->section->id,
                    'name' => $s->section->name,
                    'grade' => $s->section->grade->name ?? null
                ])->unique('id')->values()->toArray();
            }

            return response()->json([
                'data' => ModuloResource::collection($result['data'])->resolve(),
                'meta' => [
                    'materia' => [
                        'id' => $result['materia']->id,
                        'name' => $result['materia']->name,
                    ],
                    'sections' => $sections
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Materia no encontrada'], 404);
        } catch (\Exception $e) {
            Log::error('Error en modulos index: ' . $e->getMessage());
            return response()->json(['message' => 'Error al obtener los módulos'], 500);
        }
    }

    /**
     * Crear un nuevo módulo
     * POST /modulos
     */
    public function store(StoreModuloRequest $request)
    {
        try {
            // Authorization ya verificada por route middleware (role:profesor)
            // + Policy adicional para verificar acceso a la materia
            $user = request()->user();
            $validated = $request->validated();
            
            if (!$this->moduloService->canAccessMateria($user, $validated['materia_id'])) {
                return response()->json(['message' => 'No tienes acceso a esta materia'], 403);
            }

            $modulo = $this->moduloService->create($validated);

            return response()->json([
                'message' => 'Módulo creado correctamente',
                'data' => new ModuloResource($modulo)
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error en modulos store: ' . $e->getMessage());
            return response()->json(['message' => 'Error al crear el módulo'], 500);
        }
    }

    /**
     * Actualizar módulo
     * PUT /modulos/{modulo}
     */
    public function update(UpdateModuloRequest $request, Modulo $modulo)
    {
        try {
            // F1: Verificar ownership - profesor debe enseñar esta materia
            $user = request()->user();
            if (!$this->moduloService->canAccessMateria($user, $modulo->materia_id)) {
                return response()->json(['message' => 'No tienes acceso a este módulo'], 403);
            }

            $validated = $request->validated();

            $modulo = $this->moduloService->update($modulo, $validated);

            return response()->json([
                'message' => 'Módulo actualizado correctamente',
                'data' => new ModuloResource($modulo)
            ]);

        } catch (\Exception $e) {
            Log::error('Error en modulos update: ' . $e->getMessage());
            return response()->json(['message' => 'Error al actualizar el módulo'], 500);
        }
    }

    /**
     * Eliminar módulo
     * DELETE /modulos/{modulo}
     */
    public function destroy(Modulo $modulo)
    {
        try {
            // F1: Verificar ownership - profesor debe enseñar esta materia
            $user = request()->user();
            if (!$this->moduloService->canAccessMateria($user, $modulo->materia_id)) {
                return response()->json(['message' => 'No tienes acceso a este módulo'], 403);
            }

            // Verificar si tiene tareas asociadas antes de eliminar
            $tieneTareas = DB::table('tareas')
                ->where('modulo_id', $modulo->id)
                ->exists();
            
            if ($tieneTareas) {
                return response()->json([
                    'message' => 'No puedes eliminar el módulo porque tiene tareas asociadas. Elimina las tareas primero.'
                ], 409);
            }

            $this->moduloService->delete($modulo);

            return response()->json(['message' => 'Módulo eliminado correctamente']);

        } catch (\Exception $e) {
            Log::error('Error en modulos destroy: ' . $e->getMessage());
            return response()->json(['message' => 'Error al eliminar el módulo'], 500);
        }
    }

    /**
     * Subir material a un módulo
     * POST /modulos/{modulo}/materiales
     */
    public function uploadMaterial(StoreMaterialRequest $request, Modulo $modulo)
    {
        try {
            // Authorization verificada por route middleware (role:profesor)
            $user = request()->user();
            $validated = $request->validated();

            $material = $this->materialService->upload($modulo, $user, $validated);

            return response()->json([
                'message' => 'Material subido correctamente',
                'data' => new MaterialResource($material->load('usuario'))
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error en uploadMaterial: ' . $e->getMessage());
            return response()->json(['message' => 'Error al subir el material'], 500);
        }
    }

    /**
     * Eliminar material
     * DELETE /materiales/{material}
     */
    public function destroyMaterial(Material $material)
    {
        try {
            // Authorization verificada por route middleware (role:profesor)
            $this->materialService->delete($material);

            return response()->json(['message' => 'Material eliminado correctamente']);

        } catch (\Exception $e) {
            Log::error('Error en destroyMaterial: ' . $e->getMessage());
            return response()->json(['message' => 'Error al eliminar el material'], 500);
        }
    }

    /**
     * Descargar material
     * GET /materiales/{material}/descargar
     */
    public function downloadMaterial(Material $material)
    {
        try {
            // Authorization via Policy - cualquier usuario autenticado con acceso
            $this->authorize('view', $material);

            $result = $this->materialService->getDownloadInfo($material);

            if (!$result['success']) {
                return response()->json(['message' => $result['message']], 404);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error en downloadMaterial: ' . $e->getMessage());
            return response()->json(['message' => 'Error al descargar el material'], 500);
        }
    }
}
