<?php

namespace App\Services;

use App\Models\Grade;
use App\Traits\LogsActivity;

class GradeService
{
    use LogsActivity;
    
    /**
     * Listar grados
     * Nota: Ya no filtra por school_year_id (el grado es independiente del año)
     */
    public function index(array $filters): array
    {
        $query = Grade::with(['sections']);

        $paginated = $query->ordered()->paginate(15);

        return [
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
        ];
    }

    /**
     * Crear grado
     * Nota: Ya no requiere school_year_id
     */
    public function create(array $data): Grade
    {
        // Eliminar school_year_id si viene en los datos (ya no es válido)
        unset($data['school_year_id']);

        // Mapear 'order' (nombre de la API) → 'grade_order' (nombre de la BD)
        if (isset($data['order'])) {
            $data['grade_order'] = $data['order'];
            unset($data['order']);
        }

        $grade = Grade::create($data);

        $this->logActivity($this->logEvent('grade', 'created'), $grade, null, true);

        return $grade->load(['sections']);
    }

    /**
     * Ver grado
     */
    public function show(Grade $grade): Grade
    {
        return $grade->load(['sections']);
    }

    /**
     * Actualizar grado
     */
    public function update(Grade $grade, array $data): Grade
    {
        // Eliminar school_year_id si viene en los datos (ya no es válido)
        unset($data['school_year_id']);

        // Mapear 'order' (nombre de la API) → 'grade_order' (nombre de la BD)
        if (isset($data['order'])) {
            $data['grade_order'] = $data['order'];
            unset($data['order']);
        }

        $oldData = $grade->toArray();

        $grade->update($data);

        $changes = $this->getChanges($oldData, $data);
        $this->logActivity($this->logEvent('grade', 'updated'), $grade, $changes);

        return $grade->load(['sections']);
    }

    /**
     * Eliminar grado (hard delete - eliminación permanente)
     */
    public function delete(Grade $grade): void
    {
        // Verificar si hay secciones asociadas
        if ($grade->sections()->count() > 0) {
            throw new \Exception('No se puede eliminar un grado que tiene secciones asociadas');
        }
        
        // Eliminación permanente (hard delete)
        $grade->forceDelete();

        $this->logActivity($this->logEvent('grade', 'deleted'), $grade, null, true);
    }
}
