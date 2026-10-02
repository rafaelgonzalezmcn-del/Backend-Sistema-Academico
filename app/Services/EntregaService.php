<?php

namespace App\Services;

use App\Support\ArchivoPrivado;
use App\Models\Entrega;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Traits\LogsActivity;

class EntregaService
{
    use LogsActivity;
    /**
     * Obtener entregas de una tarea (para profesor/admin)
     */
    public function getEntregasTarea($tarea): array
    {
        // Debug: registrar tipo de tarea
        Log::info('getEntregasTarea - Tipo de tarea: ' . gettype($tarea) . ' - Es array: ' . (is_array($tarea) ? 'SI' : 'NO'));
        
        // Verificar que tarea NO es un array - si es, convertir o manejar
        if (is_array($tarea)) {
            Log::error('Tarea es un array,convirtiendo a objeto. Datos: ' . json_encode($tarea));
            // Si es array con 'id', crear un objeto stdClass
            if (isset($tarea['id'])) {
                $tarea = (object) $tarea;
            } else {
                return [
                    'data' => [],
                    'tarea' => null,
                    'resumen' => [
                        'total_estudiantes' => 0,
                        'total_entregados' => 0,
                        'total_faltan' => 0,
                        'error' => 'Error interno: tarea inválida (array sin id)'
                    ]
                ];
            }
        }
        
        // Verificar que tarea es un objeto
        if (!is_object($tarea)) {
            Log::error('Tarea no es objeto ni array: ' . gettype($tarea));
            return [
                'data' => [],
                'tarea' => null,
                'resumen' => [
                    'total_estudiantes' => 0,
                    'total_entregados' => 0,
                    'total_faltan' => 0,
                    'error' => 'Error interno: tarea inválida'
                ]
            ];
        }

        // Verificar que tarea tiene id
        if (!isset($tarea->id)) {
            Log::error('Tarea no tiene id. Tarea: ' . json_encode($tarea));
            return [
                'data' => [],
                'tarea' => $tarea,
                'resumen' => [
                    'total_estudiantes' => 0,
                    'total_entregados' => 0,
                    'total_faltan' => 0,
                    'error' => 'Error interno: tarea sin id'
                ]
            ];
        }

        Log::info('Tarea ID: ' . $tarea->id);
        
        $entregas = Entrega::where('tarea_id', $tarea->id)
            ->with(['estudiante' => function($query) {
                $query->select('id', 'first_name', 'last_name', 'email');
            }])
            ->get();

        // Cargar el módulo si no está cargado
        if (!$tarea->relationLoaded('modulo')) {
            $tarea->load('modulo');
        }

        // Obtener el módulo (puede ser null, objeto o array)
        $modulo = $tarea->modulo;
        
        // Si es un array (de resource), convertir a objeto
        if (is_array($modulo)) {
            $modulo = (object) $modulo;
        }

        // Verificar que el módulo existe
        if (!$modulo) {
            return [
                'data' => [],
                'tarea' => $tarea,
                'resumen' => [
                    'total_estudiantes' => 0,
                    'total_entregados' => 0,
                    'total_faltan' => 0,
                    'error' => 'La tarea no tiene un módulo asociado'
                ]
            ];
        }

        // Obtener el materia_id (puede estar como propiedad o en array)
        $materiaId = null;
        if (is_object($modulo)) {
            $materiaId = $modulo->materia_id ?? null;
        } elseif (is_array($modulo)) {
            $materiaId = $modulo['materia_id'] ?? null;
        }

        // Verificar que la materia existe
        if (!$materiaId) {
            return [
                'data' => [],
                'tarea' => $tarea,
                'resumen' => [
                    'total_estudiantes' => 0,
                    'total_entregados' => 0,
                    'total_faltan' => 0,
                    'error' => 'El módulo no tiene una materia asociada'
                ]
            ];
        }
        
        // Obtener las secciones que tienen esta materia asignada
        $seccionesConMateria = \App\Models\ClassSchedule::where('subject_id', $materiaId)
            ->pluck('section_id')
            ->unique();
        
        // Obtener todos los estudiantes activos en esas secciones
        $estudiantes = \App\Models\User::whereIn('section_id', $seccionesConMateria)
            ->whereHas('role', function($query) {
                $query->where('name', 'estudiante');
            })
            ->where('activo', true)
            ->get(['id', 'first_name', 'last_name', 'email', 'section_id']);

        // Crear lista completa de estudiantes con su estado de entrega
        $estudiantesConEstado = $estudiantes->map(function($estudiante) use ($entregas) {
            $entrega = $entregas->firstWhere('estudiante_id', $estudiante->id);
            return [
                'estudiante' => $estudiante,
                'entrega' => $entrega ? [
                    'id' => $entrega->id,
                    'archivo' => $entrega->archivo,
                    'archivo_url' => ArchivoPrivado::url($entrega->archivo),
                    'fecha_entrega' => $entrega->fecha_entrega,
                    'nota' => $entrega->nota,
                    'observaciones' => $entrega->observaciones
                ] : null,
                'ha_entregado' => $entrega !== null
            ];
        });

        $totalEstudiantes = $estudiantes->count();
        $totalEntregados = $entregas->count();
        $totalFaltan = $totalEstudiantes - $totalEntregados;

        return [
            'data' => $estudiantesConEstado,
            'tarea' => $tarea,
            'resumen' => [
                'total_estudiantes' => $totalEstudiantes,
                'total_entregados' => $totalEntregados,
                'total_faltan' => $totalFaltan
            ]
        ];
    }

    /**
     * Obtener entrega resumen para estudiante (solo su propia entrega + stats)
     */
    public function getEntregaResumenEstudiante($tarea, User $estudiante): array
    {
        // Manejar si tarea es un array
        if (is_array($tarea)) {
            if (isset($tarea['id'])) {
                $tarea = (object) $tarea;
            } else {
                return [
                    'mi_entrega' => null,
                    'ha_entregado' => false,
                    'resumen' => [
                        'total_estudiantes' => 0,
                        'total_entregados' => 0,
                        'total_faltan' => 0
                    ],
                    'error' => 'Error interno: tarea inválida'
                ];
            }
        }

        // Obtener entrega del estudiante
        $miEntrega = Entrega::where('tarea_id', $tarea->id)
            ->where('estudiante_id', $estudiante->id)
            ->first();

        // Cargar el módulo si no está cargado
        if (!$tarea->relationLoaded('modulo')) {
            $tarea->load('modulo');
        }

        // Obtener el módulo (puede ser null, objeto o array)
        $modulo = $tarea->modulo;
        
        // Si es un array (de resource), convertir a objeto
        if (is_array($modulo)) {
            $modulo = (object) $modulo;
        }

        // Obtener materia_id
        $materiaId = null;
        if ($modulo && is_object($modulo)) {
            $materiaId = $modulo->materia_id ?? null;
        }

        // Verificar que el módulo y materia existen
        if (!$modulo || !$materiaId) {
            return [
                'mi_entrega' => null,
                'ha_entregado' => false,
                'resumen' => [
                    'total_estudiantes' => 0,
                    'total_entregados' => 0,
                    'total_faltan' => 0
                ],
                'error' => 'La tarea no tiene un módulo o materia asociada'
            ];
        }

        // Obtener stats de la clase usando $materiaId ya obtenido
        $seccionesConMateria = \App\Models\ClassSchedule::where('subject_id', $materiaId)
            ->pluck('section_id')
            ->unique();
        
        $totalEstudiantes = \App\Models\User::whereIn('section_id', $seccionesConMateria)
            ->whereHas('role', function($query) {
                $query->where('name', 'estudiante');
            })
            ->where('activo', true)
            ->count();

        $totalEntregados = Entrega::where('tarea_id', $tarea->id)->count();

        return [
            'mi_entrega' => $miEntrega, // Devolver el modelo, no array
            'ha_entregado' => $miEntrega !== null,
            'resumen' => [
                'total_estudiantes' => $totalEstudiantes,
                'total_entregados' => $totalEntregados,
                'total_faltan' => $totalEstudiantes - $totalEntregados
            ]
        ];
    }

     /**
     * Obtener mi entrega de una tarea (para estudiante)
     */
    public function getMiEntrega($tarea, User $estudiante): ?Entrega
    {
        // Manejar si tarea es un array
        if (is_array($tarea)) {
            if (!isset($tarea['id'])) {
                return null;
            }
            $tareaId = $tarea['id'];
        } elseif (is_object($tarea) && isset($tarea->id)) {
            $tareaId = $tarea->id;
        } else {
            return null;
        }

        return Entrega::where('tarea_id', $tareaId)
            ->where('estudiante_id', $estudiante->id)
            ->first();
    }

    /**
     * Crear o actualizar una entrega (F2-T3)
     * USA TRANSACTION:確保原子性 - si falla el guardado, archivo no se pierde
     * Maneja unique constraint violation para double submit
     */
    public function createOrUpdateEntrega(Tarea $tarea, User $estudiante, array $data): Entrega
    {
        try {
            return DB::transaction(function () use ($tarea, $estudiante, $data) {
                // Verificar si ya existe una entrega para esta tarea y estudiante
                $entregaExistente = Entrega::where('tarea_id', $tarea->id)
                    ->where('estudiante_id', $estudiante->id)
                    ->first();

                $archivoAEliminar = null;
                $esNueva = false;

                if ($entregaExistente) {
                    // Una entrega calificada ya no se puede reemplazar
                    // (el profesor calificó ese archivo, no otro)
                    if ($entregaExistente->nota !== null) {
                        throw new \DomainException('No puedes reenviar una entrega que ya fue calificada.');
                    }
                    // Eliminar archivo anterior si existe y hay uno nuevo
                    if (isset($data['archivo']) && $entregaExistente->archivo) {
                        $archivoAEliminar = $entregaExistente->archivo;
                    }
                    $entrega = $entregaExistente;
                    // La fecha de entrega es la del último archivo enviado.
                    // Antes se conservaba la primera: se podía reenviar fuera de
                    // plazo y la entrega seguía figurando como "a tiempo".
                    $entrega->fecha_entrega = now();
                } else {
                    $entrega = new Entrega();
                    $entrega->tarea_id = $tarea->id;
                    $entrega->estudiante_id = $estudiante->id;
                    // Fecha desde Laravel (misma zona horaria que fecha_limite),
                    // no desde el reloj de la base de datos
                    $entrega->fecha_entrega = now();
                    $esNueva = true;
                }

                // Manejar archivo si se proporcionó
                if (isset($data['archivo'])) {
                    $entrega->archivo = $data['archivo'];
                }

                $entrega->save();

                // Eliminar archivo anterior DESPUÉS de guardar exitosamente
                ArchivoPrivado::eliminar($archivoAEliminar);

                // Log activity después de que la operación sea exitosa
                $event = $esNueva ? 'created' : 'updated';
                $this->logActivity(
                    $this->logEvent('entrega', $event),
                    $entrega,
                    null,
                    true
                );

                return $entrega->load(['tarea', 'estudiante']);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // F2-T3: Manejar unique constraint violation (double submit)
            if ($e->getCode() === '23505') { // PostgreSQL unique violation
                throw new \Exception('Ya tienes una entrega registrada para esta tarea. No puedes enviar múltiples entregas.');
            }
            throw $e;
        }
    }

    /**
     * Calificar una entrega (F2-T1 + F2-T4)
     * Usa DB::transaction + lockForUpdate para prevenir race conditions
     * y garantizar atomicidad de: save + log
     * 
     * @param int $entregaId ID de la entrega
     * @return Entrega
     */
    public function calificarEntrega(int $entregaId, array $data): Entrega
    {
        // F2-T1: lockForUpdate solo bloquea la fila dentro de una transacción.
        // Antes se llamaba sin transacción y el bloqueo no tenía efecto.
        [$lockedEntrega, $oldData] = DB::transaction(function () use ($entregaId, $data) {
            $lockedEntrega = Entrega::lockForUpdate()->find($entregaId);
            if (!$lockedEntrega) {
                throw new \Exception('Entrega no encontrada');
            }
            $oldData = ['nota' => $lockedEntrega->nota, 'observaciones' => $lockedEntrega->observaciones];

            // Guardar calificación
            $lockedEntrega->nota = $data['nota'];
            $lockedEntrega->observaciones = $data['observaciones'] ?? null;
            $lockedEntrega->save();

            return [$lockedEntrega, $oldData];
        });

        // F2-T4: Log después de guardar exitosamente
        // Si el log falla, la calificación ya está guardada ( tradeoff aceptable )
        try {
            $changes = $this->getChanges($oldData, $data);
            $this->logActivity($this->logEvent('entrega', 'graded'), $lockedEntrega, $changes);
        } catch (\Exception $e) {
            // Log error but don't fail the operation
            Log::error('Error al registrar actividad de calificación: ' . $e->getMessage());
        }

        // Cargar relaciones para retornar
        return $lockedEntrega->load(['estudiante' => function($query) {
            $query->select('id', 'first_name', 'last_name', 'email');
        }]);
    }

    /**
     * Crear entrega sin archivo (para calificar estudiante que no entregó)
     */
    public function crearEntregaVacia(Tarea $tarea, User $estudiante, array $data): Entrega
    {
        $entrega = new Entrega();
        $entrega->tarea_id = $tarea->id;
        $entrega->estudiante_id = $estudiante->id;
        $entrega->archivo = null;
        $entrega->fecha_entrega = now();
        $entrega->nota = $data['nota'];
        $entrega->observaciones = $data['observaciones'] ?? null;
        $entrega->save();

        $this->logActivity($this->logEvent('entrega', 'graded'), $entrega, null, true);

        return $entrega->load(['estudiante' => function($query) {
            $query->select('id', 'first_name', 'last_name', 'email');
        }]);
    }

    /**
     * Eliminar una entrega
     */
    public function deleteEntrega(Entrega $entrega): void
    {
        // Eliminar archivo si existe
        ArchivoPrivado::eliminar($entrega->archivo);

        $entrega->delete();

        $this->logActivity($this->logEvent('entrega', 'deleted'), $entrega, null, true);
    }

    /**
     * Guardar archivo de entrega
     */
    public function guardarArchivo($file): string
    {
        return ArchivoPrivado::guardar($file, 'entregas');
    }

    /**
     * Obtener URL de descarga
     */
    public function getDownloadUrl(Entrega $entrega): array
    {
        if (!ArchivoPrivado::existe($entrega->archivo)) {
            return [
                'success' => false,
                'message' => 'Archivo no encontrado'
            ];
        }

        return [
            'success' => true,
            'download_url' => ArchivoPrivado::url($entrega->archivo),
            'filename' => pathinfo($entrega->archivo, PATHINFO_BASENAME)
        ];
    }
}
