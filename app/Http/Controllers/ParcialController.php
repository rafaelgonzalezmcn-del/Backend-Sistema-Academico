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
        $parciales = $this->parcialService->getByModulo($moduloId);
        return response()->json(['data' => ParcialResource::collection(collect($parciales))->resolve()]);
    }

    public function store(StoreParcialRequest $request)
    {
        $this->authorize('create', Parcial::class);
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
        $parametros = $this->parcialService->getParametros($parcialId);
        return response()->json(['data' => ParametroResource::collection(collect($parametros))->resolve()]);
    }

    public function storeParametro(StoreParametroRequest $request)
    {
        $this->authorize('create', Parametro::class);
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
        $creados = $this->parcialService->asegurarParciales($moduloId);

        return response()->json([
            'message' => $creados ? 'Parciales creados correctamente' : 'Los parciales ya existían',
            'creados' => $creados
        ]);
    }

    public function resumenNotas($moduloId)
    {
        $this->authorize('viewResumen', Parcial::class);
        Modulo::findOrFail($moduloId);

        return response()->json($this->parcialService->getResumenNotas($moduloId));
    }

    public function misNotas($moduloId)
    {
        $this->authorize('viewMisNotas', Parcial::class);
        
        $modulo = Modulo::findOrFail($moduloId);
        $user = request()->user();

        return response()->json($this->parcialService->getMisNotas($user->id, $moduloId));
    }
}
