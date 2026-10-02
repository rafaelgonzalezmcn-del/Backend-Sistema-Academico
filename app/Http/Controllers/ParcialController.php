<?php

namespace App\Http\Controllers;

use App\Models\Parcial;
use App\Models\Parametro;
use App\Models\Modulo;
use App\Services\ParcialService;
use App\Http\Requests\StoreParcialRequest;
use App\Http\Requests\UpdateParcialRequest;
use App\Http\Requests\StoreParametroRequest;
use App\Http\Requests\UpdateParametroRequest;
use App\Http\Resources\ParcialResource;
use App\Http\Resources\ParametroResource;

class ParcialController extends Controller
{
    public function __construct(private ParcialService $parcialService) {}

    // ==================== PARCIAL ====================

    public function index($moduloId)
    {
        $this->authorize('verModulo', [Parcial::class, Modulo::findOrFail($moduloId)]);
        $parciales = $this->parcialService->getByModulo($moduloId);
        return response()->json(['data' => ParcialResource::collection(collect($parciales))->resolve()]);
    }

    public function store(StoreParcialRequest $request)
    {
        $this->authorize('create', Parcial::class);
        $this->authorize('gestionarModulo', [Parcial::class, Modulo::findOrFail($request->validated()['modulo_id'])]);
        $parcial = $this->parcialService->create($request->validated());

        return response()->json([
            'message' => 'Parcial creado correctamente con parámetros por defecto',
            'data' => new ParcialResource($parcial)
        ], 201);
    }

    public function update(UpdateParcialRequest $request, Parcial $parcial)
    {
        $this->authorize('update', $parcial);
        $parcial = $this->parcialService->update($parcial, $request->validated());

        return response()->json([
            'message' => 'Parcial actualizado correctamente',
            'data' => new ParcialResource($parcial)
        ]);
    }

    // ==================== PARÁMETRO ====================

    public function parametros($parcialId)
    {
        $parcial = Parcial::with('modulo')->findOrFail($parcialId);
        $this->authorize('verModulo', [Parcial::class, $parcial->modulo]);
        $parametros = $this->parcialService->getParametros($parcialId);
        return response()->json(['data' => ParametroResource::collection(collect($parametros))->resolve()]);
    }

    public function storeParametro(StoreParametroRequest $request)
    {
        $this->authorize('create', Parametro::class);
        $parcial = Parcial::with('modulo')->findOrFail($request->validated()['parcial_id']);
        $this->authorize('gestionarModulo', [Parcial::class, $parcial->modulo]);
        $parametro = $this->parcialService->createParametro($request->validated());

        return response()->json([
            'message' => 'Parámetro creado correctamente',
            'data' => new ParametroResource($parametro)
        ], 201);
    }

    public function updateParametro(UpdateParametroRequest $request, Parametro $parametro)
    {
        $this->authorize('update', $parametro);
        $parametro = $this->parcialService->updateParametro($parametro, $request->validated());

        return response()->json([
            'message' => 'Parámetro actualizado correctamente',
            'data' => new ParametroResource($parametro)
        ]);
    }

    public function destroyParametro(Parametro $parametro)
    {
        $this->authorize('delete', $parametro);
        $this->parcialService->deleteParametro($parametro);

        return response()->json(['message' => 'Parámetro eliminado correctamente']);
    }

    // ==================== NOTAS ====================

    public function forzarCreacion($moduloId)
    {
        $modulo = Modulo::findOrFail($moduloId);
        // Solo admin o el profesor de la materia (antes cualquier usuario autenticado)
        $this->authorize('gestionarModulo', [Parcial::class, $modulo]);
        $creados = $this->parcialService->asegurarParciales($moduloId);

        return $this->success(
            ['creados' => $creados],
            $creados ? 'Parciales creados correctamente' : 'Los parciales ya existían'
        );
    }

    public function resumenNotas($moduloId)
    {
        $this->authorize('viewResumen', Parcial::class);
        // El profesor solo puede ver las notas de los módulos de sus materias
        $this->authorize('gestionarModulo', [Parcial::class, Modulo::findOrFail($moduloId)]);

        $resumen = $this->parcialService->getResumenNotas($moduloId);

        // data = notas por estudiante; meta.parciales = estructura de parciales del módulo
        return $this->success($resumen['data'], null, ['parciales' => $resumen['parciales']]);
    }

    public function misNotas($moduloId)
    {
        $this->authorize('viewMisNotas', Parcial::class);
        
        $modulo = Modulo::findOrFail($moduloId);
        $this->authorize('verModulo', [Parcial::class, $modulo]);
        $user = request()->user();

        $misNotas = $this->parcialService->getMisNotas($user->id, $moduloId);

        return $this->success($misNotas['data'], null, ['nota_final' => $misNotas['nota_final']]);
    }
}
