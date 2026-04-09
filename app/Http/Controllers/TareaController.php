<?php

namespace App\Http\Controllers;

use App\Models\Tarea;
use App\Models\Modulo;
use App\Http\Requests\StoreTareaRequest;
use App\Http\Requests\UpdateTareaRequest;
use App\Http\Resources\TareaResource;
use App\Services\TareaService;
use Illuminate\Http\Request;

class TareaController extends Controller
{
    public function __construct(private TareaService $tareaService) {}

    /**
     * Obtener tareas de un módulo
     */
    public function index($moduloId)
    {
        $modulo = Modulo::findOrFail($moduloId);
        $this->authorize('viewAny', Tarea::class);

        $user = request()->user();
        $esEstudiante = $user?->role?->name === 'estudiante';

        $tareas = $this->tareaService->getByModulo($moduloId, $esEstudiante ? $user->id : null);

        return response()->json(['data' => TareaResource::collection(collect($tareas))->resolve()]);
    }

    /**
     * Obtener tareas de un parámetro
     */
    public function tareasPorParametro($parametroId)
    {
        $tareas = $this->tareaService->getByParametro($parametroId);
        return response()->json(['data' => TareaResource::collection(collect($tareas))->resolve()]);
    }

    /**
     * Crear tarea
     */
    public function store(StoreTareaRequest $request)
    {
        $this->authorize('create', Tarea::class);
        
        $modulo = Modulo::findOrFail($request->validated('modulo_id'));
        $this->authorize('view', $modulo);

        $tarea = $this->tareaService->create($request->validated(), $request);

        return response()->json([
            'message' => 'Tarea creada correctamente',
            'data' => new TareaResource($tarea)
        ], 201);
    }

    /**
     * Actualizar tarea
     */
    public function update(UpdateTareaRequest $request, Tarea $tarea)
    {
        $this->authorize('update', $tarea);

        $tarea = $this->tareaService->update($tarea, $request->validated(), $request);

        return response()->json([
            'message' => 'Tarea actualizada correctamente',
            'data' => new TareaResource($tarea)
        ]);
    }

    /**
     * Eliminar tarea
     */
    public function destroy(Tarea $tarea)
    {
        $this->authorize('delete', $tarea);
        $this->tareaService->delete($tarea);

        return response()->json(['message' => 'Tarea eliminada correctamente']);
    }

    /**
     * Mostrar tarea específica
     */
    public function show(Tarea $tarea)
    {
        $this->authorize('view', $tarea);
        $tarea->estado = $tarea->getEstado();

        return response()->json(['data' => new TareaResource($tarea)]);
    }

    /**
     * Descargar archivo de tarea
     */
    public function downloadArchivo(Tarea $tarea)
    {
        $this->authorize('view', $tarea);

        if (!$this->tareaService->archivoExiste($tarea->archivo_ruta)) {
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }

        return response()->json($this->tareaService->getDownloadUrl($tarea));
    }
}
