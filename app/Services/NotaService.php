<?php

namespace App\Services;

use App\Models\Tarea;
use App\Models\Entrega;
use App\Models\Parametro;
use App\Models\Parcial;
use Illuminate\Support\Collection;

class NotaService
{
    /**
     * Calcular nota de un estudiante en un parcial específico
     * 
     * Soporta recibir el objeto Parcial o el ID
     * 
     * @param int $estudianteId
     * @param Parcial|int $parcial Objeto Parcial o ID
     * @return array
     */
    public function calcularNotaParcial($estudianteId, $parcial)
    {
        // Si es ID, cargar el parcial
        if (is_numeric($parcial)) {
            $parcial = Parcial::with('parametros')->find($parcial);
        }
        
        if (!$parcial) {
            return ['error' => 'Parcial no encontrado'];
        }

        $notaFinal = 0;
        $porcentajeTotal = 0;
        $detalles = [];
        
        // Obtener la nota máxima del parcial (ej: 10 puntos)
        $notaMaximaParcial = $parcial->nota_maxima ?? 100;

        foreach ($parcial->parametros as $parametro) {
            $tareas = Tarea::where('parametro_id', $parametro->id)
                ->where('parcial_id', $parcial->id)
                ->get();

            if ($tareas->isEmpty()) continue;

            $tareaIds = $tareas->pluck('id');
            $entregas = Entrega::where('estudiante_id', $estudianteId)
                ->whereIn('tarea_id', $tareaIds)
                ->whereNotNull('nota')
                ->get();

            if ($entregas->isEmpty()) continue;

            // Calcular nota en escala original (NO normalizar a 100)
            // La nota ya está en la escala de la tarea (puntaje_maximo)
            $notaParametro = 0;
            $totalPuntajeMaximo = 0;
            
            foreach ($entregas as $entrega) {
                $tarea = $tareas->find($entrega->tarea_id);
                $puntajeMaximo = $tarea->puntaje_maximo ?? 10;
                // Normalizar a escala del parcial
                $notaNormalizada = ($entrega->nota / $puntajeMaximo) * $notaMaximaParcial;
                $notaParametro += $notaNormalizada;
                $totalPuntajeMaximo += $puntajeMaximo;
            }
            $notaParametro = $notaParametro / $entregas->count();

            // La ponderación se calcula sobre 100 (porcentaje del parámetro)
            $notaPonderada = ($notaParametro * $parametro->porcentaje) / 100;
            $notaFinal += $notaPonderada;
            $porcentajeTotal += $parametro->porcentaje;

            $detalles[] = [
                'parametro' => $parametro->nombre,
                'tipo' => $parametro->tipo,
                'porcentaje' => $parametro->porcentaje,
                'nota_parametro' => round($notaParametro, 2),
                'nota_ponderada' => round($notaPonderada, 2),
                'nota_maxima_parcial' => $notaMaximaParcial,
                'tareas_entregadas' => $entregas->count(),
                'tareas_totales' => $tareas->count()
            ];
        }

        return [
            'nota_final' => round($notaFinal, 2),
            'detalles' => $detalles
        ];
    }

    /**
     * Calcular nota final de un estudiante en una materia
     * 
     * @param int $estudianteId
     * @param int $moduloId
     * @return array
     */
    public function calcularNotaFinal($estudianteId, $moduloId)
    {
        $parciales = Parcial::where('modulo_id', $moduloId)
            ->with('parametros')
            ->orderBy('numero')
            ->get();

        $notaFinal = 0;
        $parcialesDetallados = [];

        foreach ($parciales as $parcial) {
            $resultadoParcial = $this->calcularNotaParcial($estudianteId, $parcial);
            $notaFinal += $resultadoParcial['nota_final'] ?? 0;
            $parcialesDetallados[] = $resultadoParcial;
        }

        return [
            'nota_final' => round($notaFinal, 2),
            'parciales' => $parcialesDetallados
        ];
    }

    /**
     * Obtener resumen de notas de todos los estudiantes de una materia
     * 
     * @param int $moduloId
     * @return array
     */
    public function obtenerResumenNotas($moduloId)
    {
        $parcial = Parcial::where('modulo_id', $moduloId)->first();
        
        if (!$parcial) {
            return ['error' => 'No hay parciales configurados'];
        }

        // Obtener estudiantes inscritos en la materia
        $modulo = \App\Models\Modulo::find($moduloId);
        $secciones = \App\Models\ClassSchedule::where('subject_id', $modulo->materia_id)
            ->pluck('section_id')
            ->unique();

        $estudiantes = \App\Models\User::whereIn('section_id', $secciones)
            ->whereHas('role', fn($q) => $q->where('name', 'estudiante'))
            ->where('activo', true)
            ->get();

        $resumen = [];

        foreach ($estudiantes as $estudiante) {
            $notaParcial = $this->calcularNotaParcial($estudiante->id, $parcial);
            $resumen[] = [
                'estudiante' => [
                    'id' => $estudiante->id,
                    'nombre' => $estudiante->first_name . ' ' . $estudiante->last_name
                ],
                'nota' => $notaParcial['nota_final'] ?? 0,
                'detalles' => $notaParcial['detalles'] ?? []
            ];
        }

        // Ordenar por nota descendente
        usort($resumen, fn($a, $b) => $b['nota'] <=> $a['nota']);

        return $resumen;
    }
}
