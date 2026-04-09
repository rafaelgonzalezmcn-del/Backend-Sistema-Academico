<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSectionRequest;
use App\Http\Requests\UpdateSectionRequest;
use App\Http\Resources\SectionResource;
use App\Models\Section;
use App\Services\SectionService;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function __construct(private SectionService $sectionService)
    {
    }

    /**
     * Listar secciones con paginación
     */
    public function index(Request $request)
    {
        // Rutas públicas - no requiere authorize
        // Si solo se necesita el conteo
        if ($request->has('count_only')) {
            return response()->json([
                'total' => $this->sectionService->count($request->all())
            ]);
        }

        $sections = $this->sectionService->index($request->all());
        
        return response()->json([
            'data' => SectionResource::collection($sections)->resolve(),
            'meta' => [
                'current_page' => $sections->currentPage(),
                'last_page' => $sections->lastPage(),
                'total' => $sections->total(),
                'per_page' => $sections->perPage(),
            ],
        ]);
    }

    /**
     * Crear sección
     */
    public function store(StoreSectionRequest $request)
    {
        $this->authorize('create', Section::class);

        try {
            $section = $this->sectionService->create($request->validated());
            return response()->json([
                'data' => new SectionResource($section),
                'message' => 'Sección creada correctamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Ver sección
     */
    public function show(Section $section)
    {
        // Rutas públicas - no requiere authorize
        return response()->json([
            'data' => new SectionResource($this->sectionService->show($section))
        ]);
    }

    /**
     * Actualizar sección
     */
    public function update(UpdateSectionRequest $request, Section $section)
    {
        $this->authorize('update', $section);

        try {
            $section = $this->sectionService->update($section, $request->validated());
            return response()->json([
                'data' => new SectionResource($section),
                'message' => 'Sección actualizada correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Eliminar sección (soft delete)
     */
    public function destroy(Section $section)
    {
        $this->authorize('delete', $section);

        $this->sectionService->delete($section);

        return response()->json(['message' => 'Sección eliminada correctamente']);
    }
}
