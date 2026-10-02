<?php

/**
 * Crear parámetros en un parcial (POST /parciales/{id}/parametros).
 * Cada parcial trae 4 parámetros estándar; el profesor puede agregar
 * parámetros personalizados (tipo "otro"), p. ej. "Proyecto" o "Laboratorio".
 */

use App\Models\Entrega;
use App\Models\Parametro;
use App\Services\NotaService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->e = escenarioAcademico();
    $this->url = "/api/parciales/{$this->e->parcial->id}/parametros";
    Sanctum::actingAs($this->e->profesor);
});

test('el profesor agrega un parámetro personalizado sin enviar parcial_id ni tipo', function () {
    // Igual que lo envía el frontend: el parcial va en la URL
    $this->postJson($this->url, ['nombre' => 'Proyecto final', 'porcentaje' => 20, 'nota_maxima_default' => 10])
        ->assertStatus(201)
        ->assertJsonPath('data.nombre', 'Proyecto final')
        ->assertJsonPath('data.tipo', 'otro');

    expect(Parametro::where('parcial_id', $this->e->parcial->id)->count())->toBe(5); // 4 estándar + 1
});

test('se pueden agregar varios parámetros personalizados al mismo parcial', function () {
    $this->postJson($this->url, ['nombre' => 'Proyecto', 'porcentaje' => 10])->assertStatus(201);
    $this->postJson($this->url, ['nombre' => 'Laboratorio', 'porcentaje' => 10])->assertStatus(201);

    expect(Parametro::where('parcial_id', $this->e->parcial->id)->where('tipo', 'otro')->count())->toBe(2);
});

test('no se permiten dos parámetros con el mismo nombre en un parcial', function () {
    $this->postJson($this->url, ['nombre' => 'Proyecto'])->assertStatus(201);
    $this->postJson($this->url, ['nombre' => 'Proyecto'])
        ->assertStatus(422)->assertJsonValidationErrors('nombre');
});

test('un tipo estándar repetido responde 422 (antes era un error 500)', function () {
    $this->postJson($this->url, ['nombre' => 'Más exámenes', 'tipo' => 'examenes'])
        ->assertStatus(422)->assertJsonValidationErrors('tipo');
});

test('el nombre es obligatorio y se le quitan etiquetas HTML', function () {
    $this->postJson($this->url, [])->assertStatus(422)->assertJsonValidationErrors('nombre');

    $this->postJson($this->url, ['nombre' => '<b>Exposición</b>'])
        ->assertStatus(201)->assertJsonPath('data.nombre', 'Exposición');
});

test('el parámetro nuevo respeta el máximo de 100 % del parcial', function () {
    $this->e->parcial->parametros()->where('tipo', 'examenes')->update(['porcentaje' => 90]);

    $this->postJson($this->url, ['nombre' => 'Proyecto', 'porcentaje' => 20])
        ->assertStatus(422)->assertJsonValidationErrors('porcentaje');
    $this->postJson($this->url, ['nombre' => 'Proyecto', 'porcentaje' => 10])->assertStatus(201);
});

test('un profesor ajeno o un estudiante NO pueden agregar parámetros', function () {
    Sanctum::actingAs($this->e->otroProfesor);
    $this->postJson($this->url, ['nombre' => 'Intruso'])->assertStatus(403);

    Sanctum::actingAs($this->e->estudiante);
    $this->postJson($this->url, ['nombre' => 'Intruso'])->assertStatus(403);

    expect(Parametro::where('nombre', 'Intruso')->exists())->toBeFalse();
});

test('renombrar un parámetro con un nombre ya usado en el parcial responde 422', function () {
    $tareas = $this->e->parcial->parametros()->where('tipo', 'tareas')->first();
    $this->putJson("/api/parametros/{$tareas->id}", ['nombre' => 'Exámenes'])
        ->assertStatus(422)->assertJsonValidationErrors('nombre');

    // Guardar con su propio nombre sí se permite
    $this->putJson("/api/parametros/{$tareas->id}", ['nombre' => 'Tareas'])->assertOk();
});

test('las tareas de un parámetro personalizado cuentan en la nota del parcial', function () {
    $this->e->parcial->update(['nota_maxima' => 10]);
    $id = $this->postJson($this->url, ['nombre' => 'Proyecto', 'porcentaje' => 50])->json('data.id');

    $tarea = crearTarea($this->e, ['parametro_id' => $id, 'puntaje_maximo' => 20]);
    Entrega::create(['tarea_id' => $tarea->id, 'estudiante_id' => $this->e->estudiante->id, 'fecha_entrega' => now(), 'nota' => 16]);

    // 16/20 → 8.0 en escala 10 → 8.0 × 50 % = 4.0
    $r = app(NotaService::class)->calcularNotaParcial($this->e->estudiante->id, $this->e->parcial->fresh('parametros'));
    expect($r['nota_final'])->toEqual(4.0);
});
