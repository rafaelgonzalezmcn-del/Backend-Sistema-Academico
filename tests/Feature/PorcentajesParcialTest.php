<?php

/**
 * Regla: los porcentajes de los parámetros de un parcial suman como máximo 100 %,
 * sin importar si el parcial es sobre 10 o sobre 100 puntos.
 */

use App\Models\Parametro;
use App\Models\Parcial;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->e = escenarioAcademico();
    $this->params = $this->e->parcial->parametros()->get()->keyBy('tipo');
    // Distribución inicial válida: 20 + 30 + 0 + 50 = 100
    $this->params['actividades_clase']->update(['porcentaje' => 20]);
    $this->params['tareas']->update(['porcentaje' => 30]);
    $this->params['examenes']->update(['porcentaje' => 50]);
    Sanctum::actingAs($this->e->profesor);
});

test('se acepta una distribución que suma exactamente 100 %', function () {
    // 20 + 30 + 0 + 50 → bajar exámenes a 40 y subir actuación a 10 sigue sumando 100
    $this->putJson("/api/parametros/{$this->params['examenes']->id}", ['porcentaje' => 40])->assertOk();
    $this->putJson("/api/parametros/{$this->params['actuacion']->id}", ['porcentaje' => 10])->assertOk();

    expect((float) Parametro::where('parcial_id', $this->e->parcial->id)->sum('porcentaje'))->toBe(100.0);
});

test('se rechaza un cambio que haría superar el 100 %', function () {
    $this->putJson("/api/parametros/{$this->params['examenes']->id}", ['porcentaje' => 60])
        ->assertStatus(422)
        ->assertJsonValidationErrors('porcentaje')
        ->assertJsonPath('errors.porcentaje.0', fn ($msg) => str_contains($msg, '110') && str_contains($msg, '50'));

    expect((float) $this->params['examenes']->fresh()->porcentaje)->toBe(50.0);
});

test('la regla es la misma para un parcial sobre 10 puntos', function () {
    $this->e->parcial->update(['nota_maxima' => 10]);

    // Sobre 10 no significa que los porcentajes sumen 10: siguen siendo % (máx. 100)
    $this->putJson("/api/parametros/{$this->params['actuacion']->id}", ['porcentaje' => 1])->assertStatus(422);
    $this->putJson("/api/parametros/{$this->params['examenes']->id}", ['porcentaje' => 49])->assertOk();
    $this->putJson("/api/parametros/{$this->params['actuacion']->id}", ['porcentaje' => 1])->assertOk();
});

test('editar otros campos del parámetro no se bloquea por la regla', function () {
    $this->putJson("/api/parametros/{$this->params['tareas']->id}", ['nombre' => 'Deberes'])->assertOk();
    expect($this->params['tareas']->fresh()->nombre)->toBe('Deberes');
});

test('crear un parámetro también respeta el máximo de 100 %', function () {
    // Parcial nuevo sin parámetros por defecto
    $parcial = Parcial::create(['nombre' => 'Supletorio', 'numero' => 3, 'modulo_id' => $this->e->modulo->id, 'nota_maxima' => 10]);

    $this->postJson("/api/parciales/{$parcial->id}/parametros", [
        'parcial_id' => $parcial->id, 'nombre' => 'Examen', 'tipo' => 'examenes', 'porcentaje' => 80,
    ])->assertStatus(201);

    $this->postJson("/api/parciales/{$parcial->id}/parametros", [
        'parcial_id' => $parcial->id, 'nombre' => 'Tareas', 'tipo' => 'tareas', 'porcentaje' => 30,
    ])->assertStatus(422)->assertJsonValidationErrors('porcentaje');

    expect(Parametro::where('parcial_id', $parcial->id)->count())->toBe(1);
});
