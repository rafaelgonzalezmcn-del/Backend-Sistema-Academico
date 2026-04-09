<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGradeRequest;
use App\Http\Requests\UpdateGradeRequest;
use App\Http\Resources\GradeResource;
use App\Models\Grade;
use App\Services\GradeService;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    public function __construct(private GradeService $gradeService)
    {
    }

    /**
     * Listar grados con paginación
     * Nota: Ya no se filtra por año lectivo (el grado es independiente del año)
     */
    public function index(Request $request)
    {
        // Rutas públicas - no requiere authorize
        $result = $this->gradeService->index($request->all());

        return response()->json([
            'data' => GradeResource::collection(collect($result['data']))->resolve(),
            'meta' => [
                'current_page' => $result['current_page'],
                'last_page' => $result['last_page'],
                'total' => $result['total'],
            ],
        ]);
    }

    /**
     * Crear grado
     */
    public function store(StoreGradeRequest $request)
    {
        $this->authorize('create', Grade::class);

        $grade = $this->gradeService->create($request->validated());

        return response()->json([
            'data' => new GradeResource($grade),
            'message' => 'Grado creado correctamente'
        ], 201);
    }

    /**
     * Ver grado
     */
    public function show(Grade $grade)
    {
        // Rutas públicas - no requiere authorize
        $grade = $this->gradeService->show($grade);

        return response()->json([
            'data' => new GradeResource($grade)
        ]);
    }

    /**
     * Actualizar grado
     */
    public function update(UpdateGradeRequest $request, Grade $grade)
    {
        $this->authorize('update', $grade);

        $grade = $this->gradeService->update($grade, $request->validated());

        return response()->json([
            'data' => new GradeResource($grade),
            'message' => 'Grado actualizado correctamente'
        ]);
    }

    /**
     * Eliminar grado (soft delete)
     */
    public function destroy(Grade $grade)
    {
        $this->authorize('delete', $grade);

        $this->gradeService->delete($grade);

        return response()->json(['message' => 'Grado eliminado correctamente']);
    }
}
