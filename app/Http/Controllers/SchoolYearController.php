<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSchoolYearRequest;
use App\Http\Requests\UpdateSchoolYearRequest;
use App\Http\Resources\SchoolYearResource;
use App\Models\SchoolYear;
use App\Services\SchoolYearService;
use Illuminate\Http\Request;

class SchoolYearController extends Controller
{
    public function __construct(private SchoolYearService $schoolYearService)
    {
    }

    /**
     * Listar años lectivos
     */
    public function index(Request $request)
    {
        // Rutas públicas - no requiere authorize
        // Si solo se necesita el conteo
        if ($request->has('count_only')) {
            return $this->success(['total' => $this->schoolYearService->count($request->all())]);
        }

        $schoolYears = $this->schoolYearService->index($request->all());
        
        $schoolYearsData = SchoolYearResource::collection($schoolYears)->resolve();
        
        return response()->json([
            'data' => $schoolYearsData,
            'meta' => [
                'current_page' => $schoolYears->currentPage(),
                'last_page' => $schoolYears->lastPage(),
                'total' => $schoolYears->total(),
                'per_page' => $schoolYears->perPage(),
            ],
        ]);
    }

    /**
     * Crear año lectivo
     */
    public function store(StoreSchoolYearRequest $request)
    {
        $this->authorize('create', SchoolYear::class);

        $schoolYear = $this->schoolYearService->create($request->validated());

        return response()->json([
            'data' => new SchoolYearResource($schoolYear),
            'message' => 'Año lectivo creado correctamente'
        ], 201);
    }

    /**
     * Ver año lectivo
     */
    public function show(SchoolYear $schoolYear)
    {
        // Rutas públicas - no requiere authorize
        return response()->json([
            'data' => new SchoolYearResource($this->schoolYearService->show($schoolYear))
        ]);
    }

    /**
     * Actualizar año lectivo
     */
    public function update(UpdateSchoolYearRequest $request, SchoolYear $schoolYear)
    {
        $this->authorize('update', $schoolYear);

        $schoolYear = $this->schoolYearService->update($schoolYear, $request->validated());

        return response()->json([
            'data' => new SchoolYearResource($schoolYear),
            'message' => 'Año lectivo actualizado correctamente'
        ]);
    }

    /**
     * Activar año lectivo
     */
    public function activate($id)
    {
        $schoolYear = SchoolYear::findOrFail($id);
        
        $this->authorize('activate', $schoolYear);

        $schoolYear = $this->schoolYearService->activate($schoolYear);

        return response()->json([
            'data' => new SchoolYearResource($schoolYear),
            'message' => 'Año lectivo activado correctamente'
        ]);
    }

    /**
     * Obtener el año lectivo activo actual
     */
    public function active()
    {
        // Ruta pública - no requiere authorize
        $activeYear = $this->schoolYearService->getActive();

        if (!$activeYear) {
            return response()->json([
                'message' => 'No hay año lectivo activo'
            ], 404);
        }

        return response()->json([
            'data' => new SchoolYearResource($activeYear)
        ]);
    }

    /**
     * DELETE /school-years/{schoolYear}
     * Eliminar año lectivo (soft delete)
     */
    public function destroy(SchoolYear $schoolYear)
    {
        $this->authorize('delete', $schoolYear);

        // Verificar que no tenga secciones asociadas
        if ($schoolYear->sections()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar un año lectivo con secciones asociadas'
            ], 422);
        }

        $schoolYear->delete();

        return response()->json(['message' => 'Año lectivo eliminado correctamente']);
    }
}
