<?php

namespace App\Services;

use App\Models\Parcial;
use App\Models\Parametro;
use App\Models\Modulo;
use App\Models\User;
use App\Models\ClassSchedule;
use App\Models\Tarea;
use App\Models\Entrega;
use Illuminate\Support\Facades\DB;
use App\Traits\LogsActivity;

// F4-T1: Agregar trait de logging
class ParcialService
{
    use LogsActivity;

    public function __construct(private NotaService $notaService) {}

    // ==================== PARCIAL ====================

    public function getByModulo(int $moduloId)
    {
        return Parcial::where('modulo_id', $moduloId)
            ->with('parametros')
            ->orderBy('numero')
            ->get();
    }

    /**
     * Crear parcial con parámetros por defecto (F4-T1)
     * USA TRANSACTION:确保原子性
     */
    public function create(array $validated): Parcial
    {
        return DB::transaction(function () use ($validated) {
            $parcial = Parcial::create($validated);
            $this->crearParametrosDefault($parcial, $validated['nota_maxima'] ?? 100);
            
            // F4-T1: Log de creación
            $this->logActivity($this->logEvent('parcial', 'created'), $parcial, null, true);
            
            return $parcial->load('parametros');
        });
    }

    public function update(Parcial $parcial, array $validated): Parcial
    {
        $oldData = $parcial->toArray();
        
        $parcial->update($validated);

        if (isset($validated['nota_maxima'])) {
            $parcial->parametros()->update(['nota_maxima_default' => $validated['nota_maxima']]);
        }

        // F4-T1: Log de actualización
        $changes = $this->getChanges($oldData, $parcial->toArray());
        $this->logActivity($this->logEvent('parcial', 'updated'), $parcial, $changes);

        return $parcial->load('parametros');
    }

    public function delete(Parcial $parcial): void
    {
        $parcial->delete();
        
        // F4-T1: Log de eliminación
        $this->logActivity($this->logEvent('parcial', 'deleted'), $parcial, null, true);
    }

    // ==================== PARÁMETRO ====================

    public function getParametros(int $parcialId)
    {
        return Parametro::where('parcial_id', $parcialId)
            ->where('activo', true)
            ->orderBy('tipo')
            ->get();
    }

    public function createParametro(array $validated): Parametro
    {
        return Parametro::create([
            'nombre' => $validated['nombre'],
            'tipo' => $validated['tipo'],
            'parcial_id' => $validated['parcial_id'],
            'porcentaje' => $validated['porcentaje'] ?? 0,
            'nota_maxima_default' => $validated['nota_maxima_default'] ?? 100
        ]);
    }

    public function updateParametro(Parametro $parametro, array $validated): Parametro
    {
        $parametro->update($validated);
        return $parametro;
    }

    public function deleteParametro(Parametro $parametro): void
    {
        $parametro->delete();
        
        // F4-T1: Log de eliminación de parámetro
        $this->logActivity($this->logEvent('parametro', 'deleted'), $parametro, null, true);
    }

    // ==================== NOTAS ====================

    /**
     * Asegurar que existan parciales 1 y 2
     * USA TRANSACTION:确保原子性
     */
    public function asegurarParciales(int $moduloId): bool
    {
        return DB::transaction(function () use ($moduloId) {
            $existentes = Parcial::where('modulo_id', $moduloId)->pluck('numero')->toArray();

            if (in_array(1, $existentes) && in_array(2, $existentes)) {
                return false;
            }

            $default = 100;

            if (!in_array(1, $existentes)) {
                $p1 = Parcial::create(['nombre' => 'Parcial 1', 'numero' => 1, 'modulo_id' => $moduloId, 'nota_maxima' => $default]);
                $this->crearParametrosDefault($p1, $default);
            }

            if (!in_array(2, $existentes)) {
                $p2 = Parcial::create(['nombre' => 'Parcial 2', 'numero' => 2, 'modulo_id' => $moduloId, 'nota_maxima' => $default]);
                $this->crearParametrosDefault($p2, $default);
            }

            return true;
        });
    }

    public function getResumenNotas(int $moduloId): array
    {
        $this->asegurarParciales($moduloId);
        $parciales = $this->getByModulo($moduloId);
        $estudiantes = $this->getEstudiantes($moduloId);

        $resultado = [];

        foreach ($estudiantes as $estudiante) {
            $notaFinal = 0;
            $parcialesData = [];

            foreach ($parciales as $parcial) {
                $notaParcial = $this->notaService->calcularNotaParcial($estudiante->id, $parcial);
                $notaFinal += $notaParcial['nota_final'] ?? 0;
                $parcialesData[] = $this->buildParcialData($parcial, $notaParcial, $estudiante->id);
            }

            $resultado[] = [
                'estudiante' => ['id' => $estudiante->id, 'nombre' => $estudiante->first_name . ' ' . $estudiante->last_name, 'email' => $estudiante->email],
                'nota_final' => round($notaFinal, 2),
                'parciales' => $parcialesData
            ];
        }

        usort($resultado, fn($a, $b) => $b['nota_final'] <=> $a['nota_final']);

        return ['data' => $resultado, 'parciales' => $parciales];
    }

    public function getMisNotas(int $estudianteId, int $moduloId): array
    {
        $this->asegurarParciales($moduloId);
        $parciales = $this->getByModulo($moduloId);
        $estudiante = User::findOrFail($estudianteId);

        $resultado = [];
        $notaFinal = 0;

        foreach ($parciales as $parcial) {
            $notaParcial = $this->notaService->calcularNotaParcial($estudianteId, $parcial);
            $notaFinal += $notaParcial['nota_final'] ?? 0;
            $parcialData = $this->buildParcialData($parcial, $notaParcial, $estudianteId);
            
            $resultado[] = [
                'parcial' => ['id' => $parcial->id, 'nombre' => 'Parcial ' . $parcial->numero],
                'nota_final' => $notaParcial['nota_final'] ?? 0,
                'parametros' => $parcialData['parametros'] ?? []
            ];
        }

        return [
            'data' => [['estudiante' => ['id' => $estudiante->id, 'nombre' => $estudiante->first_name . ' ' . $estudiante->last_name, 'email' => $estudiante->email], 'nota_final' => round($notaFinal, 2), 'parciales' => $resultado]],
            'nota_final' => round($notaFinal, 2)
        ];
    }

    // ==================== HELPERS ====================

    private function crearParametrosDefault(Parcial $parcial, int $notaMaxima): void
    {
        $defaults = [
            ['nombre' => 'Actividades en clase', 'tipo' => 'actividades_clase', 'porcentaje' => 0, 'nota_maxima_default' => $notaMaxima],
            ['nombre' => 'Tareas', 'tipo' => 'tareas', 'porcentaje' => 0, 'nota_maxima_default' => $notaMaxima],
            ['nombre' => 'Actuación', 'tipo' => 'actuacion', 'porcentaje' => 0, 'nota_maxima_default' => $notaMaxima],
            ['nombre' => 'Exámenes', 'tipo' => 'examenes', 'porcentaje' => 0, 'nota_maxima_default' => $notaMaxima]
        ];

        foreach ($defaults as $param) {
            Parametro::create([
                'nombre' => $param['nombre'],
                'tipo' => $param['tipo'],
                'parcial_id' => $parcial->id,
                'porcentaje' => $param['porcentaje'],
                'nota_maxima_default' => $param['nota_maxima_default']
            ]);
        }
    }

    private function getEstudiantes(int $moduloId): array
    {
        $modulo = Modulo::find($moduloId);
        if (!$modulo) return [];

        $secciones = ClassSchedule::where('subject_id', $modulo->materia_id)->pluck('section_id')->unique()->toArray();
        if (empty($secciones)) return [];

        return User::whereIn('section_id', $secciones)->whereHas('role', fn($q) => $q->where('name', 'estudiante'))->where('activo', true)->get(['id', 'first_name', 'last_name', 'email'])->all();
    }

    private function buildParcialData(Parcial $parcial, array $notaParcial, int $estudianteId): array
    {
        $parcialArray = $parcial->toArray();
        $detallesMap = collect($notaParcial['detalles'] ?? [])->keyBy('parametro')->toArray();

        if (isset($parcialArray['parametros'])) {
            foreach ($parcialArray['parametros'] as &$param) {
                $tareas = Tarea::where('parametro_id', $param['id'])->orderBy('fecha_limite', 'asc')->get();
                
                $tareasConNotas = $tareas->map(function ($tarea) use ($estudianteId) {
                    $entrega = Entrega::where('tarea_id', $tarea->id)->where('estudiante_id', $estudianteId)->first();
                    return [
                        'id' => $tarea->id,
                        'titulo' => $tarea->titulo,
                        'nota' => $entrega ? $entrega->nota : null,
                        'puntaje_maximo' => $tarea->puntaje_maximo
                    ];
                });

                $param['tareas'] = $tareasConNotas;
                $param['tareas_totales'] = $tareas->count();
                $param['tareas_entregadas'] = 0;

                if (isset($detallesMap[$param['nombre']])) {
                    $detalle = $detallesMap[$param['nombre']];
                    $param['nota_parametro'] = $detalle['nota_parametro'] ?? null;
                    $param['nota_ponderada'] = $detalle['nota_ponderada'] ?? null;
                    $param['tareas_entregadas'] = $detalle['tareas_entregadas'] ?? 0;
                } else {
                    $param['nota_parametro'] = null;
                    $param['nota_ponderada'] = null;
                }
            }
        }

        return $parcialArray;
    }
}
