<?php

namespace App\Services;

use App\Models\Section;
use App\Traits\LogsActivity;

class SectionService
{
    use LogsActivity;

    /**
     * Listar secciones con filtros
     */
    public function index(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 15;

        $query = Section::with(['grade', 'schoolYear'])
            // Contar estudiantes activos de forma eficiente (single query)
            ->withCount([
                'students as enrolled_count' => function ($q) {
                    $q->where('activo', true);
                }
            ]);

        if (!empty($filters['grade_id'])) {
            $query->where('grade_id', $filters['grade_id']);
        }

        if (!empty($filters['school_year_id'])) {
            $query->where('school_year_id', $filters['school_year_id']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Contar secciones
     */
    public function count(array $filters): int
    {
        $query = Section::query();

        if (!empty($filters['grade_id'])) {
            $query->where('grade_id', $filters['grade_id']);
        }

        if (!empty($filters['school_year_id'])) {
            $query->where('school_year_id', $filters['school_year_id']);
        }

        return $query->count();
    }

    /**
     * Obtener secciones con información de cupos para el dashboard admin.
     * Retorna todas las secciones con enrolled_count calculado.
     */
    public function indexWithCapacity(array $filters = []): array
    {
        $query = Section::with(['grade', 'schoolYear']);

        if (!empty($filters['grade_id'])) {
            $query->where('grade_id', $filters['grade_id']);
        }

        if (!empty($filters['school_year_id'])) {
            $query->where('school_year_id', $filters['school_year_id']);
        }

        $sections = $query->get();

        return $sections->map(function ($section) {
            $data = $section->toArray();
            $data['capacity_info'] = $section->capacity_info;
            return $data;
        })->toArray();
    }

    /**
     * Crear sección
     * Valida unicidad: (grade_id, school_year_id, name) debe ser único
     */
    public function create(array $data): Section
    {
        // Validar campos requeridos
        $this->validateSectionData($data);

        // Verificar unicidad: no puede existir otra sección con mismo 
        // grado, año lectivo y nombre
        $exists = Section::where('grade_id', $data['grade_id'])
            ->where('school_year_id', $data['school_year_id'])
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            $gradeName = \App\Models\Grade::find($data['grade_id'])?->name ?? 'desconocido';
            $yearName = \App\Models\SchoolYear::find($data['school_year_id'])?->name ?? 'desconocido';
            
            throw new \Exception(
                "Ya existe la sección '{$data['name']}' para el grado '{$gradeName}' en el año lectivo '{$yearName}'"
            );
        }

        // Validar max_capacity si se proporciona
        if (isset($data['max_capacity'])) {
            if (!is_int($data['max_capacity']) || $data['max_capacity'] < 0) {
                throw new \Exception('La capacidad máxima debe ser un número entero positivo');
            }
        }

        $section = Section::create($data);

        $this->logActivity($this->logEvent('section', 'created'), $section, null, true);

        return $section->load(['grade', 'schoolYear']);
    }

    /**
     * Ver sección
     */
    public function show(Section $section): Section
    {
        return $section->loadCount([
            'students as enrolled_count' => function ($q) {
                $q->where('activo', true);
            }
        ])->load(['grade', 'schoolYear', 'students', 'classSchedules']);
    }

    /**
     * Actualizar sección
     * Valida unicidad considerando cambios en grade, school_year o name
     */
    public function update(Section $section, array $data): Section
    {
        // Obtener valores actuales o nuevos
        $newGradeId = $data['grade_id'] ?? $section->grade_id;
        $newSchoolYearId = $data['school_year_id'] ?? $section->school_year_id;
        $newName = $data['name'] ?? $section->name;

        // Verificar unicidad con los nuevos valores (excluyendo la sección actual)
        $exists = Section::where('grade_id', $newGradeId)
            ->where('school_year_id', $newSchoolYearId)
            ->where('name', $newName)
            ->where('id', '!=', $section->id)
            ->exists();

        if ($exists) {
            $gradeName = \App\Models\Grade::find($newGradeId)?->name ?? 'desconocido';
            $yearName = \App\Models\SchoolYear::find($newSchoolYearId)?->name ?? 'desconocido';
            
            throw new \Exception(
                "Ya existe la sección '{$newName}' para el grado '{$gradeName}' en el año lectivo '{$yearName}'"
            );
        }

        // Validar max_capacity si se proporciona
        if (isset($data['max_capacity'])) {
            if (!is_int($data['max_capacity']) || $data['max_capacity'] < 0) {
                throw new \Exception('La capacidad máxima debe ser un número entero positivo');
            }
            // Verificar que no se reduzca la capacidad por debajo de los estudiantes actuales
            if ($data['max_capacity'] > 0) {
                $enrolled = $section->getEnrolledCount();
                if ($data['max_capacity'] < $enrolled) {
                    throw new \Exception(
                        "No se puede reducir la capacidad a {$data['max_capacity']}. Hay {$enrolled} estudiantes matriculados."
                    );
                }
            }
        }

        $oldData = [
            'grade_id' => $section->grade_id,
            'school_year_id' => $section->school_year_id,
            'name' => $section->name,
            'max_capacity' => $section->max_capacity,
        ];

        $section->update($data);

        $changes = $this->getChanges($oldData, $data);
        $this->logActivity($this->logEvent('section', 'updated'), $section, $changes);

        return $section->load(['grade', 'schoolYear']);
    }

    /**
     * Eliminar sección
     */
    public function delete(Section $section): void
    {
        $section->delete();

        $this->logActivity($this->logEvent('section', 'deleted'), $section, null, true);
    }

    /**
     * Validar datos requeridos de la sección
     */
    private function validateSectionData(array $data): void
    {
        if (empty($data['grade_id'])) {
            throw new \Exception('El grado es requerido');
        }

        if (empty($data['school_year_id'])) {
            throw new \Exception('El año lectivo es requerido');
        }

        if (empty($data['name'])) {
            throw new \Exception('El nombre de la sección es requerido');
        }

        // Verificar que el grado existe
        if (!\App\Models\Grade::where('id', $data['grade_id'])->exists()) {
            throw new \Exception('El grado seleccionado no existe');
        }

        // Verificar que el año lectivo existe
        if (!\App\Models\SchoolYear::where('id', $data['school_year_id'])->exists()) {
            throw new \Exception('El año lectivo seleccionado no existe');
        }
    }
}
