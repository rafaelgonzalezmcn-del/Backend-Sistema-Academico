<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalificarEntregaRequest;
use App\Http\Requests\StoreEntregaRequest;
use App\Http\Requests\UpdateEntregaRequest;
use App\Http\Resources\EntregaResource;
use App\Models\Entrega;
use App\Models\Tarea;
use App\Services\EntregaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EntregaController extends Controller
{
    public function __construct(
        private EntregaService $entregaService
    ) {}

    /**
     * Obtener entregas de una tarea
     * - Profesores/Admin ven todas las entregas
     * - Estudiantes ven solo stats (no detalles de otros)
     */
    public function index(Tarea $tarea)
    {
        // Debug: verificar que tarea es un objeto
        if (is_array($tarea)) {
            Log::error('Tarea recibida es un array: ' . json_encode($tarea));
            return response()->json(['message' => 'Error interno: tarea inválida'], 500);
        }

        try {
            $this->authorize('viewAny', [Entrega::class, $tarea]);
        } catch (\Exception $e) {
            Log::error('Error de autorización en entregas index: ' . $e->getMessage());
            return response()->json(['message' => 'No tienes permiso para ver estas entregas'], 403);
        }

        try {
            $user = auth()->user();
            
            // Verificar si es estudiante
            $esEstudiante = $user->role && $user->role->name === 'estudiante';

            if ($esEstudiante) {
                // Estudiantes ven solo stats (resumen)
                $data = $this->entregaService->getEntregaResumenEstudiante($tarea, $user);
                
                // Cargar relaciones de forma segura
                $tarea->load(['modulo', 'modulo.materia']);
                
                return response()->json([
                    'data' => [], // No entregamos detalles a estudiantes
                    'mi_entrega' => $data['mi_entrega'] ? new EntregaResource($data['mi_entrega']) : null,
                    'ha_entregado' => $data['ha_entregado'],
                    'tarea' => $tarea,
                    'resumen' => $data['resumen']
                ]);
            }

            // Profesores/Admin ven todas las entregas
            $data = $this->entregaService->getEntregasTarea($tarea);

            // No transformar con EntregaResource ya que getEntregasTarea devuelve 
            // una estructura personalizada de estudiantes con sus entregas
            return response()->json($data);

        } catch (\Exception $e) {
            Log::error('Error en entregas index: ' . $e->getMessage() . ' - Stack: ' . $e->getTraceAsString());
            return response()->json(['message' => 'Error al obtener las entregas: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener mi entrega de una tarea (para estudiantes)
     */
    public function show(Tarea $tarea)
    {
        $this->authorize('create', [Entrega::class, $tarea]);

        try {
            $user = auth()->user();
            $entrega = $this->entregaService->getMiEntrega($tarea, $user);

            if (!$entrega) {
                return response()->json(['message' => 'No has subido ninguna entrega para esta tarea'], 404);
            }

            // Agregar URL del archivo
            $entrega->archivo_url = $entrega->archivo ? asset('storage/' . $entrega->archivo) : null;

            return response()->json([
                'data' => new EntregaResource($entrega->load(['tarea']))
            ]);

        } catch (\Exception $e) {
            Log::error('Error en entregas show: ' . $e->getMessage());
            return response()->json(['message' => 'Error al obtener la entrega'], 500);
        }
    }

    /**
     * Subir entrega de tarea (F3-T4)
     */
    public function store(StoreEntregaRequest $request, Tarea $tarea)
    {
        $this->authorize('create', [Entrega::class, $tarea]);

        try {
            // F3-T4: Verificar que la tarea tiene módulo válido
            if (!$tarea->modulo_id) {
                return response()->json([
                    'message' => 'No puedes entregar una tarea que no tiene módulo asociado'
                ], 400);
            }

            $user = auth()->user();
            $validated = $request->validated();

            // Guardar archivo
            $archivoPath = $this->entregaService->guardarArchivo($request->file('archivo'));

            // Crear o actualizar entrega
            $entrega = $this->entregaService->createOrUpdateEntrega(
                $tarea,
                $user,
                ['archivo' => $archivoPath]
            );

            return response()->json([
                'message' => 'Entrega subida correctamente',
                'data' => new EntregaResource($entrega)
            ], 201);

        } catch (\Exception $e) {
            // F2-T3: Manejar error de entrega duplicada
            if (str_contains($e->getMessage(), 'Ya tienes una entrega registrada')) {
                return response()->json([
                    'message' => $e->getMessage()
                ], 409); // 409 Conflict
            }
            
            Log::error('Error en entregas store: ' . $e->getMessage());
            return response()->json(['message' => 'Error al subir la entrega'], 500);
        }
    }

    /**
     * Descargar archivo de entrega
     */
    public function download(Entrega $entrega)
    {
        $this->authorize('view', $entrega);

        try {
            $result = $this->entregaService->getDownloadUrl($entrega);

            if (!$result['success']) {
                return response()->json(['message' => $result['message']], 404);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error en entregas download: ' . $e->getMessage());
            return response()->json(['message' => 'Error al descargar el archivo'], 500);
        }
    }

    /**
     * Calificar una entrega existente
     */
    public function calificar(CalificarEntregaRequest $request, Entrega $entrega)
    {
        $this->authorize('update', $entrega);

        try {
            $validated = $request->validated();
            
            // Cargar la tarea para obtener puntaje máximo
            $entrega->load('tarea');
            
            // Validar que la entrega tenga tarea asociada
            if (!$entrega->tarea) {
                return response()->json([
                    'message' => 'La entrega no tiene una tarea asociada'
                ], 400);
            }

            // Obtener el puntaje máximo de la tarea para validación dinámica
            $maxNota = $entrega->tarea->puntaje_maximo ?? 100;

            // Validar nota con max dinámico
            if ($validated['nota'] > $maxNota) {
                return response()->json([
                    'message' => "La nota no puede exceder el puntaje máximo de {$maxNota}"
                ], 422);
            }

            // F2-T1 + F2-T4: Calificación con locking y transacción
            $entrega = $this->entregaService->calificarEntrega($entrega->id, $validated);

            return response()->json([
                'message' => 'Entrega calificada correctamente',
                'data' => new EntregaResource($entrega)
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Entrega no encontrada en calificar: ' . $e->getMessage());
            return response()->json(['message' => 'Entrega no encontrada'], 404);
        } catch (\Exception $e) {
            Log::error('Error en entregas calificar: ' . $e->getMessage());
            return response()->json(['message' => 'Error al calificar la entrega'], 500);
        }
    }

    /**
     * Calificar directamente (crear entrega sin archivo para estudiante que no entregó)
     */
    public function calificarDirecto(CalificarEntregaRequest $request)
    {
        try {
            $user = auth()->user();
            $validated = $request->validated();

            // Obtener la tarea
            $tarea = Tarea::with('modulo')->find($validated['tarea_id']);
            if (!$tarea) {
                return response()->json(['message' => 'Tarea no encontrada'], 404);
            }

            // Authorization: Check if user can access this tarea's modulo (similar to update)
            // Create a temporary Entrega for authorization check
            $tempEntrega = new Entrega();
            $tempEntrega->tarea_id = $tarea->id;
            $this->authorize('update', $tempEntrega);

            // Obtener el puntaje máximo
            $maxNota = $tarea->puntaje_maximo ?? 100;

            // Validar nota con max dinámico
            if ($validated['nota'] > $maxNota) {
                return response()->json([
                    'message' => "La nota no puede exceder el puntaje máximo de {$maxNota}"
                ], 422);
            }

            // Buscar entrega existente o crear nueva
            $entrega = Entrega::where('tarea_id', $validated['tarea_id'])
                ->where('estudiante_id', $validated['estudiante_id'])
                ->first();

            if ($entrega) {
                // Actualizar entrega existente
            $entrega = $this->entregaService->calificarEntrega($entrega->id, $validated);
                $message = 'Calificación actualizada correctamente';
            } else {
                // Crear nueva entrega sin archivo
                $estudiante = \App\Models\User::find($validated['estudiante_id']);
                if (!$estudiante) {
                    return response()->json(['message' => 'Estudiante no encontrado'], 404);
                }

                $entrega = $this->entregaService->crearEntregaVacia($tarea, $estudiante, $validated);
                $message = 'Calificación guardada correctamente';
            }

            return response()->json([
                'message' => $message,
                'data' => new EntregaResource($entrega)
            ]);

        } catch (\Exception $e) {
            Log::error('Error en entregas calificarDirecto: ' . $e->getMessage());
            return response()->json(['message' => 'Error al calificar'], 500);
        }
    }

    /**
     * Eliminar una entrega
     */
    public function destroy(Entrega $entrega)
    {
        $this->authorize('delete', $entrega);

        try {
            // Verificar que la entrega fue antes de la fecha límite
            $tarea = $entrega->tarea;
            if ($tarea && $tarea->fecha_limite) {
                if (new \DateTime($entrega->fecha_entrega) > new \DateTime($tarea->fecha_limite)) {
                    return response()->json(['message' => 'No puedes eliminar una entrega realizada después de la fecha límite'], 400);
                }
            }

            // Verificar que la entrega no esté calificada (F1-T2)
            if ($entrega->nota !== null) {
                return response()->json(['message' => 'No puedes eliminar una entrega ya calificada'], 400);
            }

            $this->entregaService->deleteEntrega($entrega);

            return response()->json([
                'message' => 'Entrega eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en entregas destroy: ' . $e->getMessage());
            return response()->json(['message' => 'Error al eliminar la entrega'], 500);
        }
    }
}
