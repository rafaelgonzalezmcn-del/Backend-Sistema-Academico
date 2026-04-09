<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ActivityLogService
{
    /**
     * Listar registros de actividad con filtros
     */
    public function index(array $filters): LengthAwarePaginator
    {
        $query = ActivityLog::with(['user'])->orderBy('created_at', 'desc');

        // Filtro por tipo de sujeto
        if (!empty($filters['subject_type'])) {
            $query->where('subject_type', $filters['subject_type']);
        }

        // Filtro por usuario
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Filtro por búsqueda en descripción
        if (!empty($filters['search'])) {
            $query->where('description', 'LIKE', '%' . $filters['search'] . '%');
        }

        return $query->paginate(20);
    }

    /**
     * Contar registros de actividad
     */
    public function count(array $filters): int
    {
        $query = ActivityLog::query();

        if (!empty($filters['subject_type'])) {
            $query->where('subject_type', $filters['subject_type']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['search'])) {
            $query->where('description', 'LIKE', '%' . $filters['search'] . '%');
        }

        return $query->count();
    }
}
