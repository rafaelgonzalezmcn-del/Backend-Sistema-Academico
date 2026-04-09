<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function __construct(private ActivityLogService $activityLogService)
    {
    }

    /**
     * Listar registros de actividad con paginación.
     */
    public function index(Request $request)
    {
        // Verificar que el usuario es admin
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }
        
        $logs = $this->activityLogService->index($request->all());
        
        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
            ],
        ]);
    }

    /**
     * GET /activity-logs/count
     * Contar registros de actividad (solo admin)
     */
    public function count(Request $request)
    {
        // Verificar que el usuario es admin
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }
        
        return response()->json([
            'total' => $this->activityLogService->count($request->all())
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
