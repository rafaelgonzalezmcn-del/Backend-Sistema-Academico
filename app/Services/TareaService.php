<?php

namespace App\Services;

use App\Support\ArchivoPrivado;
use App\Models\Tarea;
use App\Models\Parametro;
use App\Models\Modulo;
use App\Models\Entrega;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Traits\LogsActivity;

class TareaService
{
    use LogsActivity;
    /**
     * Crear tarea - método principal
     */
    public function create(array $validated, ?Request $request = null): Tarea
    {
        $tareaData = $this->prepararDatos($validated, $request);
        
        // Manejar archivo - separar en campos individuales
        if (!empty($tareaData['archivo'])) {
            $tareaData['archivo_ruta'] = $tareaData['archivo']['ruta'];
            $tareaData['archivo_nombre'] = $tareaData['archivo']['nombre'];
            $tareaData['archivo_tamano'] = $tareaData['archivo']['tamano'];
            unset($tareaData['archivo']);
        }
        
        $tarea = Tarea::create($tareaData);
        
        $this->logActivity($this->logEvent('tarea', 'created'), $tarea, null, true);
        
        $tarea->estado = $tarea->getEstado();
        return $tarea;
    }

    /**
     * Actualizar tarea - método principal
     * USA TRANSACTION:確保原子性 - si falla DB, archivo no se elimina
     */
    public function update(Tarea $tarea, array $validated, ?Request $request = null): Tarea
    {
        $archivoAEliminar = null;
        
        // Preparar datos primero (sin modificar nada)
        $tareaData = $this->prepararDatos($validated, $request, true, $tarea);
        
        // Manejar archivo - guardar referencia para eliminar después
        if (!empty($tareaData['archivo'])) {
            $archivoAEliminar = $tarea->archivo_ruta;
            $tareaData['archivo_ruta'] = $tareaData['archivo']['ruta'];
            $tareaData['archivo_nombre'] = $tareaData['archivo']['nombre'];
            $tareaData['archivo_tamano'] = $tareaData['archivo']['tamano'];
            unset($tareaData['archivo']);
        }

        $oldData = $tarea->toArray();
        
        // Ejecutar en transacción
        DB::transaction(function () use ($tarea, $tareaData) {
            $tarea->update($tareaData);
        });

        // Eliminar archivo anterior DESPUÉS de que DB update sea exitoso
        if ($archivoAEliminar) {
            $this->eliminarArchivo($archivoAEliminar);
        }

        $changes = $this->getChanges($oldData, $tareaData);
        $this->logActivity($this->logEvent('tarea', 'updated'), $tarea, $changes);

        $tarea->estado = $tarea->getEstado();
        return $tarea->fresh();
    }

    /**
     * Eliminar tarea - método principal
     * USA TRANSACTION:確保 atomicidad - si falla DB, archivo no se elimina
     */
    public function delete(Tarea $tarea): void
    {
        $archivoAEliminar = $tarea->archivo_ruta;
        
        DB::transaction(function () use ($tarea) {
            $tarea->delete();
        });

        // Eliminar archivo DESPUÉS de que DB delete sea exitoso
        $this->eliminarArchivo($archivoAEliminar);

        $this->logActivity($this->logEvent('tarea', 'deleted'), $tarea, null, true);
    }

    /**
     * Obtener tareas por módulo
     */
    public function getByModulo(int $moduloId, ?int $estudianteId = null)
    {
        $tareas = Tarea::where('modulo_id', $moduloId)
            ->orderBy('fecha_limite', 'asc')
            ->get()
            ->map(function ($tarea) {
                $tarea->estado = $tarea->getEstado();
                return $tarea;
            });

        // F5-T2: Optimizar N+1 - obtener todas las entregas del estudiante en una sola query
        if ($estudianteId) {
            $tareaIds = $tareas->pluck('id');
            $entregas = Entrega::whereIn('tarea_id', $tareaIds)
                ->where('estudiante_id', $estudianteId)
                ->get()
                ->keyBy('tarea_id');
            
            $tareas = $tareas->map(function ($tarea) use ($entregas) {
                $entrega = $entregas->get($tarea->id);
                
                $tarea->mi_entrega = $entrega ? [
                    'id' => $entrega->id,
                    'archivo' => $entrega->archivo,
                    'fecha_entrega' => $entrega->fecha_entrega,
                    'nota' => $entrega->nota,
                    'observaciones' => $entrega->observaciones
                ] : null;
                
                return $tarea;
            });
        }

        return $tareas;
    }

    /**
     * Obtener tareas por parámetro
     */
    public function getByParametro(int $parametroId)
    {
        return Tarea::where('parametro_id', $parametroId)
            ->orderBy('fecha_limite', 'asc')
            ->get()
            ->map(function ($tarea) {
                $tarea->estado = $tarea->getEstado();
                return $tarea;
            });
    }

    /**
     * Obtener tarea por ID
     */
    public function find(int $id): ?Tarea
    {
        return Tarea::find($id);
    }

    /**
     * Preparar datos de tarea
     * IMPORTANTE: La validación de integridad se hace en el Service como safety net
     */
    private function prepararDatos(array $validated, ?Request $request = null, bool $isUpdate = false, ?Tarea $existingTarea = null): array
    {
        // ============================================================
        // VALIDACIÓN DE INTEGRIDAD - Safety net del Service
        // (También se valida en FormRequest, esto es redundante pero seguro)
        // ============================================================
        
        $moduloId = $validated['modulo_id'] ?? null;
        $parcialId = $validated['parcial_id'] ?? null;
        $parametroId = $validated['parametro_id'] ?? null;
        
        // Validar que parcial pertenezca al módulo
        if ($parcialId && $moduloId) {
            $parcial = \App\Models\Parcial::find($parcialId);
            if ($parcial && $parcial->modulo_id != $moduloId) {
                throw new \InvalidArgumentException(
                    'El parcial seleccionado no pertenece al módulo elegido.'
                );
            }
        }
        
        // Validar que parámetro pertenezca al parcial
        if ($parametroId && $parcialId) {
            $parametro = \App\Models\Parametro::find($parametroId);
            if ($parametro && $parametro->parcial_id != $parcialId) {
                throw new \InvalidArgumentException(
                    'El parámetro seleccionado no pertenece al parcial elegido.'
                );
            }
        }
        
        // ============================================================
        // Fin de validación de integridad
        // ============================================================
        
        // Sanitización XSS: se quitan etiquetas HTML (Vue escapa el resto al mostrar)
        // En una edición solo se cambian los campos enviados; los demás se conservan.
        $enviado = fn (string $campo) => !$isUpdate || array_key_exists($campo, $validated);

        $data = [];

        if ($enviado('titulo')) {
            $data['titulo'] = strip_tags($validated['titulo'] ?? '');
        }
        if ($enviado('descripcion')) {
            $data['descripcion'] = !empty($validated['descripcion']) ? strip_tags($validated['descripcion']) : null;
        }
        if ($enviado('fecha_limite') && ($validated['fecha_limite'] ?? null) !== null) {
            $data['fecha_limite'] = $validated['fecha_limite'];
        }
        if (!$isUpdate) {
            $data['puntaje_maximo'] = $this->obtenerNotaMaxima($validated);
        } elseif (!empty($validated['puntaje_maximo'])) {
            $data['puntaje_maximo'] = $validated['puntaje_maximo'];
        }
        if ($enviado('parcial_id')) {
            $data['parcial_id'] = $parcialId;
        }
        if ($enviado('parametro_id')) {
            $data['parametro_id'] = $parametroId;
        }

        if (!$isUpdate) {
            $data['modulo_id'] = $validated['modulo_id'];
        }

        // Manejar archivo si existe
        if ($request && $request->hasFile('archivo')) {
            $data['archivo'] = $this->guardarArchivo($request->file('archivo'));
        }

        return $data;
    }

    /**
     * Obtener nota máxima del parámetro o usar valor por defecto
     */
    private function obtenerNotaMaxima(array $validated): int
    {
        if (!empty($validated['puntaje_maximo'])) {
            return $validated['puntaje_maximo'];
        }

        if (!empty($validated['parametro_id'])) {
            $parametro = Parametro::find($validated['parametro_id']);
            if ($parametro) {
                return $parametro->nota_maxima_default ?? 100;
            }
        }

        return 100;
    }

    /**
     * Guardar archivo de tarea
     */
    private function guardarArchivo($file): array
    {
        $originalName = $file->getClientOriginalName();
        $path = ArchivoPrivado::guardar($file, 'tareas');

        return [
            'ruta' => $path,
            'nombre' => $originalName,
            'tamano' => $file->getSize()
        ];
    }

    /**
     * Eliminar archivo de tarea
     */
    public function eliminarArchivo(?string $ruta): void
    {
        ArchivoPrivado::eliminar($ruta);
    }

    /**
     * Verificar si archivo existe
     */
    public function archivoExiste(?string $ruta): bool
    {
        return ArchivoPrivado::existe($ruta);
    }

    /**
     * Obtener URL de descarga
     */
    public function getDownloadUrl(Tarea $tarea): array
    {
        return [
            'download_url' => ArchivoPrivado::url($tarea->archivo_ruta),
            'filename' => $tarea->archivo_nombre
        ];
    }
}
