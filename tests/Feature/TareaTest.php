<?php

/**
 * Pruebas de tareas: creación, edición, eliminación, consulta,
 * validaciones y permisos por rol / materia.
 */

use App\Models\Tarea;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->e = escenarioAcademico();
});

function datosTarea(object $e, array $extra = []): array
{
    return array_merge([
        'titulo' => 'Ensayo sobre la célula',
        'descripcion' => 'Máximo 2 páginas',
        'fecha_limite' => now()->addWeek()->format('Y-m-d H:i:s'),
        'modulo_id' => $e->modulo->id,
        'puntaje_maximo' => 20,
        'parcial_id' => $e->parcial->id,
        'parametro_id' => $e->parametro->id,
    ], $extra);
}

describe('Crear tarea', function () {

    test('el profesor de la materia crea una tarea', function () {
        Sanctum::actingAs($this->e->profesor);

        $this->postJson('/api/tareas', datosTarea($this->e))
            ->assertStatus(201)
            ->assertJsonPath('message', 'Tarea creada correctamente')
            ->assertJsonPath('data.titulo', 'Ensayo sobre la célula')
            ->assertJsonPath('data.puntaje_maximo', 20);

        expect(Tarea::count())->toBe(1);
    });

    test('se puede adjuntar un archivo y queda en el disco privado', function () {
        Sanctum::actingAs($this->e->profesor);

        $this->post('/api/tareas', datosTarea($this->e, [
            'archivo' => UploadedFile::fake()->create('enunciado.pdf', 200, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertStatus(201);

        $tarea = Tarea::first();
        expect($tarea->archivo_nombre)->toBe('enunciado.pdf')
            ->and($tarea->archivo_ruta)->toStartWith('tareas/');
        Storage::disk('local')->assertExists($tarea->archivo_ruta);
    });

    test('sin puntaje máximo toma el valor por defecto del parámetro', function () {
        Sanctum::actingAs($this->e->profesor);

        $this->postJson('/api/tareas', datosTarea($this->e, ['puntaje_maximo' => null]))
            ->assertStatus(201)
            ->assertJsonPath('data.puntaje_maximo', $this->e->parametro->nota_maxima_default);
    });

    test('se eliminan etiquetas HTML del título y la descripción', function () {
        Sanctum::actingAs($this->e->profesor);

        $this->postJson('/api/tareas', datosTarea($this->e, [
            'titulo' => '<script>alert(1)</script>Tarea',
            'descripcion' => '<b>Importante</b>',
        ]))->assertStatus(201);

        $tarea = Tarea::first();
        expect($tarea->titulo)->not->toContain('<script>')
            ->and($tarea->descripcion)->toBe('Importante');
    });

    test('validaciones: título, fecha y módulo son obligatorios', function () {
        Sanctum::actingAs($this->e->profesor);

        $this->postJson('/api/tareas', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['titulo', 'fecha_limite', 'modulo_id']);
    });

    test('validaciones: puntaje máximo entre 1 y 1000', function () {
        Sanctum::actingAs($this->e->profesor);

        $this->postJson('/api/tareas', datosTarea($this->e, ['puntaje_maximo' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors('puntaje_maximo');
        $this->postJson('/api/tareas', datosTarea($this->e, ['puntaje_maximo' => 1001]))
            ->assertStatus(422)->assertJsonValidationErrors('puntaje_maximo');
    });

    test('el parcial debe pertenecer al módulo', function () {
        $otroModulo = \App\Models\Modulo::create(['nombre' => 'Módulo 2', 'materia_id' => $this->e->materia->id]);
        app(\App\Services\ParcialService::class)->asegurarParciales($otroModulo->id);
        $parcialAjeno = \App\Models\Parcial::where('modulo_id', $otroModulo->id)->first();

        Sanctum::actingAs($this->e->profesor);
        $this->postJson('/api/tareas', datosTarea($this->e, [
            'parcial_id' => $parcialAjeno->id,
            'parametro_id' => null,
        ]))->assertStatus(422)->assertJsonValidationErrors('parcial_id');
    });

    test('el parámetro debe pertenecer al parcial', function () {
        $parcial2 = \App\Models\Parcial::where('modulo_id', $this->e->modulo->id)->where('numero', 2)->first();
        $parametroDelParcial2 = $parcial2->parametros()->first();

        Sanctum::actingAs($this->e->profesor);
        $this->postJson('/api/tareas', datosTarea($this->e, ['parametro_id' => $parametroDelParcial2->id]))
            ->assertStatus(422)->assertJsonValidationErrors('parametro_id');
    });

    test('archivo con tipo no permitido es rechazado', function () {
        Sanctum::actingAs($this->e->profesor);

        $this->post('/api/tareas', datosTarea($this->e, [
            'archivo' => UploadedFile::fake()->create('virus.exe', 10),
        ]), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('archivo');
    });

    test('un profesor que no dicta la materia NO puede crear tareas en ella', function () {
        Sanctum::actingAs($this->e->otroProfesor);
        $this->postJson('/api/tareas', datosTarea($this->e))->assertStatus(403);
        expect(Tarea::count())->toBe(0);
    });

    test('un estudiante NO puede crear tareas', function () {
        Sanctum::actingAs($this->e->estudiante);
        $this->postJson('/api/tareas', datosTarea($this->e))->assertStatus(403);
    });
});

describe('Editar y eliminar tarea', function () {

    test('el profesor de la materia edita una tarea', function () {
        $tarea = crearTarea($this->e);
        Sanctum::actingAs($this->e->profesor);

        $this->putJson("/api/tareas/{$tarea->id}", datosTarea($this->e, ['titulo' => 'Título nuevo']))
            ->assertOk()
            ->assertJsonPath('data.titulo', 'Título nuevo');
    });

    test('editar solo la fecha límite NO borra los demás datos', function () {
        $tarea = crearTarea($this->e, ['titulo' => 'Original', 'descripcion' => 'Desc', 'puntaje_maximo' => 25]);
        $nuevaFecha = now()->addMonth()->startOfMinute();

        Sanctum::actingAs($this->e->profesor);
        $this->putJson("/api/tareas/{$tarea->id}", ['fecha_limite' => $nuevaFecha->format('Y-m-d H:i:s')])
            ->assertOk();

        $tarea->refresh();
        expect($tarea->titulo)->toBe('Original')
            ->and($tarea->descripcion)->toBe('Desc')
            ->and($tarea->puntaje_maximo)->toBe(25)
            ->and($tarea->parcial_id)->toBe($this->e->parcial->id)
            ->and($tarea->parametro_id)->toBe($this->e->parametro->id)
            ->and($tarea->fecha_limite->equalTo($nuevaFecha))->toBeTrue();
    });

    test('editar un título con "&" lo guarda tal cual (sin &amp;)', function () {
        $tarea = crearTarea($this->e);
        Sanctum::actingAs($this->e->profesor);

        $this->putJson("/api/tareas/{$tarea->id}", ['titulo' => 'Física & Química <b>2</b>'])->assertOk();
        expect($tarea->fresh()->titulo)->toBe('Física & Química 2');
    });

    test('no se puede dejar el título vacío al editar', function () {
        $tarea = crearTarea($this->e);
        Sanctum::actingAs($this->e->profesor);

        $this->putJson("/api/tareas/{$tarea->id}", ['titulo' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('titulo');
    });

    test('reemplazar el archivo elimina el anterior', function () {
        Sanctum::actingAs($this->e->profesor);
        $this->post('/api/tareas', datosTarea($this->e, [
            'archivo' => UploadedFile::fake()->create('v1.pdf', 10, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertStatus(201);
        $tarea = Tarea::first();
        $rutaAnterior = $tarea->archivo_ruta;

        $this->put("/api/tareas/{$tarea->id}", datosTarea($this->e, [
            'archivo' => UploadedFile::fake()->create('v2.pdf', 10, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertOk();

        Storage::disk('local')->assertMissing($rutaAnterior);
        Storage::disk('local')->assertExists($tarea->fresh()->archivo_ruta);
    });

    test('un profesor ajeno NO puede editar ni eliminar', function () {
        $tarea = crearTarea($this->e);
        Sanctum::actingAs($this->e->otroProfesor);

        $this->putJson("/api/tareas/{$tarea->id}", ['titulo' => 'Hackeado'])->assertStatus(403);
        $this->deleteJson("/api/tareas/{$tarea->id}")->assertStatus(403);
        expect($tarea->fresh()->titulo)->toBe('Tarea de prueba');
    });

    test('el profesor de la materia elimina la tarea (soft delete)', function () {
        $tarea = crearTarea($this->e);
        Sanctum::actingAs($this->e->profesor);

        $this->deleteJson("/api/tareas/{$tarea->id}")->assertOk();
        expect(Tarea::count())->toBe(0)
            ->and(Tarea::withTrashed()->count())->toBe(1);
    });
});

describe('Consultar tareas', function () {

    test('el estudiante inscrito ve las tareas del módulo con su estado de entrega', function () {
        crearTarea($this->e, ['titulo' => 'Pendiente', 'fecha_limite' => now()->addDay()]);
        crearTarea($this->e, ['titulo' => 'Vencida', 'fecha_limite' => now()->subDay()]);

        Sanctum::actingAs($this->e->estudiante);
        $res = $this->getJson("/api/modulos/{$this->e->modulo->id}/tareas")->assertOk();

        $tareas = collect($res->json('data'))->keyBy('titulo');
        expect($tareas)->toHaveCount(2)
            ->and($tareas['Pendiente']['estado'])->toBe('pendiente')
            ->and($tareas['Vencida']['estado'])->toBe('vencida')
            ->and($tareas['Pendiente']['mi_entrega'])->toBeNull();
    });

    test('un estudiante de otra sección NO ve las tareas del módulo', function () {
        crearTarea($this->e);
        Sanctum::actingAs($this->e->ajeno);
        $this->getJson("/api/modulos/{$this->e->modulo->id}/tareas")->assertStatus(403);
    });

    test('un profesor ajeno NO ve las tareas del módulo', function () {
        crearTarea($this->e);
        Sanctum::actingAs($this->e->otroProfesor);
        $this->getJson("/api/modulos/{$this->e->modulo->id}/tareas")->assertStatus(403);
    });

    test('tareas por parámetro: solo usuarios con acceso al módulo', function () {
        crearTarea($this->e);

        Sanctum::actingAs($this->e->estudiante);
        $this->getJson("/api/parametros/{$this->e->parametro->id}/tareas")
            ->assertOk()->assertJsonCount(1, 'data');

        Sanctum::actingAs($this->e->ajeno);
        $this->getJson("/api/parametros/{$this->e->parametro->id}/tareas")->assertStatus(403);
    });

    test('ver una tarea: inscrito sí, ajeno no', function () {
        $tarea = crearTarea($this->e);

        Sanctum::actingAs($this->e->estudiante);
        $this->getJson("/api/tareas/{$tarea->id}")->assertOk()->assertJsonPath('data.id', $tarea->id);

        Sanctum::actingAs($this->e->ajeno);
        $this->getJson("/api/tareas/{$tarea->id}")->assertStatus(403);
    });
});
