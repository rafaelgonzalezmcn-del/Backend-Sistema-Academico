<?php

/**
 * Pruebas del cálculo de notas (NotaService / ParcialService).
 *
 * Regla del sistema:
 *  1. Cada nota se normaliza a la escala del parcial:
 *        nota_normalizada = (nota / puntaje_maximo_tarea) * nota_maxima_parcial
 *  2. La nota de un parámetro es el promedio de sus tareas calificadas.
 *  3. Nota del parcial = Σ (nota_parametro × porcentaje / 100)
 *  4. Nota final del módulo = Σ notas de los parciales
 */

use App\Models\Entrega;
use App\Services\NotaService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->e = escenarioAcademico();

    // Parcial 1 sobre 10 puntos: Tareas 40 % y Exámenes 60 %
    $this->e->parcial->update(['nota_maxima' => 10]);
    $this->pTareas = $this->e->parcial->parametros()->where('tipo', 'tareas')->first();
    $this->pExamenes = $this->e->parcial->parametros()->where('tipo', 'examenes')->first();
    $this->pTareas->update(['porcentaje' => 40]);
    $this->pExamenes->update(['porcentaje' => 60]);

    $this->calificar = function ($tarea, $estudiante, $nota) {
        Entrega::create([
            'tarea_id' => $tarea->id,
            'estudiante_id' => $estudiante->id,
            'fecha_entrega' => now(),
            'nota' => $nota,
        ]);
    };
});

function tareaDe(object $e, $parametro, int $puntaje)
{
    return crearTarea($e, ['parametro_id' => $parametro->id, 'puntaje_maximo' => $puntaje]);
}

test('normaliza cada nota a la escala del parcial y pondera por porcentaje', function () {
    // Tareas: 8/10 → 8.0 y 10/20 → 5.0 → promedio 6.5 → 6.5 × 40 % = 2.6
    ($this->calificar)(tareaDe($this->e, $this->pTareas, 10), $this->e->estudiante, 8);
    ($this->calificar)(tareaDe($this->e, $this->pTareas, 20), $this->e->estudiante, 10);
    // Exámenes: 90/100 → 9.0 → 9.0 × 60 % = 5.4
    ($this->calificar)(tareaDe($this->e, $this->pExamenes, 100), $this->e->estudiante, 90);

    $r = app(NotaService::class)->calcularNotaParcial($this->e->estudiante->id, $this->e->parcial->fresh('parametros'));

    $detalle = collect($r['detalles'])->keyBy('tipo');
    expect($r['nota_final'])->toEqual(8.0)
        ->and($detalle['tareas']['nota_parametro'])->toEqual(6.5)
        ->and($detalle['tareas']['nota_ponderada'])->toEqual(2.6)
        ->and($detalle['examenes']['nota_parametro'])->toEqual(9.0)
        ->and($detalle['examenes']['nota_ponderada'])->toEqual(5.4);
});

test('nota perfecta en todo da la nota máxima del parcial', function () {
    ($this->calificar)(tareaDe($this->e, $this->pTareas, 10), $this->e->estudiante, 10);
    ($this->calificar)(tareaDe($this->e, $this->pExamenes, 50), $this->e->estudiante, 50);

    $r = app(NotaService::class)->calcularNotaParcial($this->e->estudiante->id, $this->e->parcial->fresh('parametros'));
    expect($r['nota_final'])->toEqual(10.0);
});

test('una entrega todavía sin calificar no afecta la nota', function () {
    ($this->calificar)(tareaDe($this->e, $this->pTareas, 10), $this->e->estudiante, 10);
    ($this->calificar)(tareaDe($this->e, $this->pTareas, 10), $this->e->estudiante, null); // sin calificar

    $r = app(NotaService::class)->calcularNotaParcial($this->e->estudiante->id, $this->e->parcial->fresh('parametros'));
    expect(collect($r['detalles'])->firstWhere('tipo', 'tareas')['nota_parametro'])->toEqual(10.0);
});

test('un parámetro con porcentaje 0 no suma a la nota', function () {
    $pActuacion = $this->e->parcial->parametros()->where('tipo', 'actuacion')->first(); // 0 %
    ($this->calificar)(tareaDe($this->e, $pActuacion, 10), $this->e->estudiante, 10);

    $r = app(NotaService::class)->calcularNotaParcial($this->e->estudiante->id, $this->e->parcial->fresh('parametros'));
    expect($r['nota_final'])->toEqual(0.0);
});

test('las notas de un estudiante no afectan las de otro', function () {
    $tarea = tareaDe($this->e, $this->pTareas, 10);
    ($this->calificar)($tarea, $this->e->estudiante, 10);
    ($this->calificar)($tarea, $this->e->companero, 5);

    $servicio = app(NotaService::class);
    $parcial = $this->e->parcial->fresh('parametros');
    expect($servicio->calcularNotaParcial($this->e->estudiante->id, $parcial)['nota_final'])->toEqual(4.0)   // 10 × 40 %
        ->and($servicio->calcularNotaParcial($this->e->companero->id, $parcial)['nota_final'])->toEqual(2.0); // 5 × 40 %
});

test('mis-notas: el estudiante ve su nota final como suma de los parciales', function () {
    // Parcial 1: 8/10 en tareas → 8 × 40 % = 3.2
    ($this->calificar)(tareaDe($this->e, $this->pTareas, 10), $this->e->estudiante, 8);

    // Parcial 2 sobre 10: Exámenes 100 %, 7/10 → 7.0
    $parcial2 = \App\Models\Parcial::where('modulo_id', $this->e->modulo->id)->where('numero', 2)->first();
    $parcial2->update(['nota_maxima' => 10]);
    $examenes2 = $parcial2->parametros()->where('tipo', 'examenes')->first();
    $examenes2->update(['porcentaje' => 100]);
    ($this->calificar)(crearTarea($this->e, [
        'parcial_id' => $parcial2->id, 'parametro_id' => $examenes2->id, 'puntaje_maximo' => 10,
    ]), $this->e->estudiante, 7);

    Sanctum::actingAs($this->e->estudiante);
    $this->getJson("/api/modulos/{$this->e->modulo->id}/notas/mis-notas")
        ->assertOk()
        ->assertJsonPath('meta.nota_final', 10.2); // 3.2 + 7.0
});

test('resumen de notas: el profesor ve a cada estudiante con su nota, de mayor a menor', function () {
    $tarea = tareaDe($this->e, $this->pTareas, 10);
    ($this->calificar)($tarea, $this->e->estudiante, 5);
    ($this->calificar)($tarea, $this->e->companero, 10);

    Sanctum::actingAs($this->e->profesor);
    $res = $this->getJson("/api/modulos/{$this->e->modulo->id}/notas/resumen")->assertOk();

    $filas = $res->json('data');
    expect($filas)->toHaveCount(2) // el estudiante de otra sección no aparece
        ->and($filas[0]['estudiante']['id'])->toBe($this->e->companero->id)
        ->and($filas[0]['nota_final'])->toEqual(4.0)
        ->and($filas[1]['nota_final'])->toEqual(2.0)
        ->and($res->json('meta.parciales'))->toHaveCount(2);
});

test('una tarea vencida sin entregar NO cuenta como 0 hasta que el profesor la califique', function () {
    // Regla del sistema: el 0 lo pone el profesor (calificación directa), no el sistema.
    $entregada = crearTarea($this->e, ['parametro_id' => $this->pTareas->id, 'puntaje_maximo' => 10, 'fecha_limite' => now()->subDay()]);
    $noEntregada = crearTarea($this->e, ['parametro_id' => $this->pTareas->id, 'puntaje_maximo' => 10, 'fecha_limite' => now()->subDay()]);
    ($this->calificar)($entregada, $this->e->estudiante, 10);

    $servicio = app(NotaService::class);
    $tareas = fn () => collect($servicio->calcularNotaParcial($this->e->estudiante->id, $this->e->parcial->fresh('parametros'))['detalles'])
        ->firstWhere('tipo', 'tareas');

    expect($tareas()['nota_parametro'])->toEqual(10.0); // la no entregada no se promedia

    // El profesor califica con 0 la tarea no entregada → ahora sí cuenta
    Sanctum::actingAs($this->e->profesor);
    $this->postJson('/api/calificar-estudiante', [
        'tarea_id' => $noEntregada->id,
        'estudiante_id' => $this->e->estudiante->id,
        'nota' => 0,
    ])->assertOk();

    expect($tareas()['nota_parametro'])->toEqual(5.0); // (10 + 0) / 2
});
