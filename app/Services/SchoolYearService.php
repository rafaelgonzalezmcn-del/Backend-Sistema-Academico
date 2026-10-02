<?php

namespace App\Services;

use App\Models\SchoolYear;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Traits\LogsActivity;

class SchoolYearService
{
    use LogsActivity;

    const CACHE_KEY_ACTIVE = 'school_year_active';
    const CACHE_TTL = 3600; // 1 hora

    /**
     * Listar años lectivos
     */
    public function index(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $perPage = \App\Support\Paginacion::porPagina($filters['per_page'] ?? null);
        $query = SchoolYear::withCount(['sections']);

        if (!empty($filters['only_active'])) {
            $query->where('active', true);
        }

        return $query->ordered()->paginate($perPage);
    }

    /**
     * Contar años lectivos
     */
    public function count(array $filters): int
    {
        $query = SchoolYear::query();

        if (!empty($filters['only_active'])) {
            $query->where('active', true);
        }

        return $query->count();
    }

    /**
     * Crear año lectivo
     * USA TRANSACTION:确保 atomicidad - si falla la creación, no quedan años deshabilitados
     */
    public function create(array $data): SchoolYear
    {
        return DB::transaction(function () use ($data) {
            // Si se crea como activo, desactivar los demás
            if (!empty($data['active'])) {
                SchoolYear::where('active', true)->update(['active' => false]);
                Cache::forget(self::CACHE_KEY_ACTIVE); // Limpiar cache
            }

            $schoolYear = SchoolYear::create($data);

            $this->logActivity($this->logEvent('school_year', 'created'), $schoolYear, null, true);

            return $schoolYear;
        });
    }

    /**
     * Ver año lectivo
     */
    public function show(SchoolYear $schoolYear): SchoolYear
    {
        // grades eliminado - los grados ya no tienen año lectivo
        return $schoolYear->load(['sections']);
    }

    /**
     * Actualizar año lectivo
     * USA TRANSACTION:确保 atomicidad - si falla update, no quedan años deshabilitados
     */
    public function update(SchoolYear $schoolYear, array $data): SchoolYear
    {
        return DB::transaction(function () use ($schoolYear, $data) {
            // Si se activa este año, desactivar los demás
            if (isset($data['active']) && $data['active']) {
                SchoolYear::where('id', '!=', $schoolYear->id)
                          ->where('active', true)
                          ->update(['active' => false]);
                Cache::forget(self::CACHE_KEY_ACTIVE); // Limpiar cache
            }

            $schoolYear->update($data);

            $this->logActivity($this->logEvent('school_year', 'updated'), $schoolYear, null, true);

            return $schoolYear;
        });
    }

    /**
     * Activar año lectivo
     */
    public function activate(SchoolYear $schoolYear): SchoolYear
    {
        return DB::transaction(function () use ($schoolYear) {
            // Desactivar todos los demás años lectivos
            SchoolYear::where('id', '!=', $schoolYear->id)->update(['active' => false]);

            // Activar el seleccionado
            $schoolYear->update(['active' => true]);

            // Limpiar cache
            Cache::forget(self::CACHE_KEY_ACTIVE);

            $this->logActivity($this->logEvent('school_year', 'activated'), $schoolYear, null, true);

            return $schoolYear;
        });
    }

    /**
     * Obtener el año lectivo activo actual (CON CACHE)
     */
    public function getActive(): ?SchoolYear
    {
        return Cache::remember(self::CACHE_KEY_ACTIVE, self::CACHE_TTL, function () {
            return SchoolYear::where('active', true)->first();
        });
    }

    /**
     * Forzar recarga del año activo (sin cache)
     */
    public function getActiveFresh(): ?SchoolYear
    {
        return SchoolYear::where('active', true)->first();
    }
}
