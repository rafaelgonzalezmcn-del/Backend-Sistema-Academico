<?php

/**
 * Pruebas de entregas: subir, reemplazar, eliminar, consultar, descargar,
 * y calificación (normal y directa), con sus reglas y permisos.
 */

use App\Models\Entrega;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->e = escenarioAcademico();
    $this->tarea = crearTarea($this->e, ['puntaje_maximo' => 10]);
});

function subirEntrega($test, $usuario, $tarea, string $nombre = 'trabajo.pdf')
{
    Sanctum::actingAs($usuario);
    return $test->post("/api/entregas/{$tarea->id}", [
        'archivo' => UploadedFile::fake()->create($nombre, 100, 'application/pdf'),
    ], ['Accept' => 'application/json']);
}

describe('Subir entrega', function () {

    test('el estudiante inscrito sube su entrega', function () {
        subirEntrega($this, $this->e->estudiante, $this->tarea)
            ->assertStatus(201)
            ->assertJsonPath('message', 'Entrega subida correctamente')
            ->assertJsonPath('data.estudiante_id', $this->e->estudiante->id);

        $entrega = Entrega::first();
        expect($entrega->archivo)->toStartWith('entregas/')
            ->and($entrega->fecha_entrega)->not->toBeNull()
            ->and($entrega->nota)->toBeNull();
        Storage::disk('local')->assertExists($entrega->archivo);
    });

    test('un estudiante de otra sección NO puede entregar', function () {
        subirEntrega($this, $this->e->ajeno, $this->tarea)->assertStatus(403);
        expect(Entrega::count())->toBe(0);
    });

    test('un profesor NO puede subir entregas', function () {
        subirEntrega($this, $this->e->profesor, $this->tarea)->assertStatus(403);
    });

    test('el archivo es obligatorio y debe tener un tipo permitido', function () {
        Sanctum::actingAs($this->e->estudiante);

        $this->postJson("/api/entregas/{$this->tarea->id}", [])
            ->assertStatus(422)->assertJsonValidationErrors('archivo');

        $this->post("/api/entregas/{$this->tarea->id}", [
            'archivo' => UploadedFile::fake()->create('script.exe', 10),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('archivo');
    });

    test('reenviar antes de ser calificada reemplaza el archivo y actualiza la fecha', function () {
        $this->travelTo(now()->subDay());
        subirEntrega($this, $this->e->estudiante, $this->tarea, 'v1.pdf')->assertStatus(201);
        $primera = Entrega::first();
        $this->travelBack();

        subirEntrega($this, $this->e->estudiante, $this->tarea, 'v2.pdf')->assertStatus(201);

        $actual = Entrega::first();
        expect(Entrega::count())->toBe(1)
            ->and($actual->archivo)->not->toBe($primera->archivo)
            ->and($actual->fecha_entrega->greaterThan($primera->fecha_entrega))->toBeTrue();
        Storage::disk('local')->assertMissing($primera->archivo);
    });

    test('NO se puede reenviar una entrega que ya fue calificada', function () {
        subirEntrega($this, $this->e->estudiante, $this->tarea, 'v1.pdf')->assertStatus(201);
        $entrega = Entrega::first();
        $entrega->update(['nota' => 8]);

        subirEntrega($this, $this->e->estudiante, $this->tarea, 'v2.pdf')->assertStatus(422);

        expect($entrega->fresh()->archivo)->toBe($entrega->archivo);
        Storage::disk('local')->assertExists($entrega->archivo);
    });

    test('una entrega fuera de plazo se acepta y queda registrada con su fecha real', function () {
        $vencida = crearTarea($this->e, ['fecha_limite' => now()->subDay()]);

        subirEntrega($this, $this->e->estudiante, $vencida)->assertStatus(201);

        $entrega = Entrega::first();
        expect($entrega->fecha_entrega->greaterThan($vencida->fecha_limite))->toBeTrue();
    });
});

describe('Consultar entregas', function () {

    test('mi-entrega: 404 si no entregó, 200 con su entrega si entregó', function () {
        Sanctum::actingAs($this->e->estudiante);
        $this->getJson("/api/entregas/{$this->tarea->id}/mi-entrega")->assertStatus(404);

        subirEntrega($this, $this->e->estudiante, $this->tarea)->assertStatus(201);

        Sanctum::actingAs($this->e->estudiante);
        $this->getJson("/api/entregas/{$this->tarea->id}/mi-entrega")
            ->assertOk()
            ->assertJsonPath('data.estudiante_id', $this->e->estudiante->id)
            ->assertJsonPath('data.tarea.id', $this->tarea->id);
    });

    test('el estudiante ve solo su estado, nunca las entregas de otros', function () {
        subirEntrega($this, $this->e->companero, $this->tarea)->assertStatus(201);

        Sanctum::actingAs($this->e->estudiante);
        $this->getJson("/api/entregas/{$this->tarea->id}")
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.ha_entregado', false)
            ->assertJsonPath('meta.mi_entrega', null)
            ->assertJsonPath('meta.resumen.total_entregados', 1);
    });

    test('el profesor ve a todos los estudiantes inscritos con su estado', function () {
        subirEntrega($this, $this->e->estudiante, $this->tarea)->assertStatus(201);

        Sanctum::actingAs($this->e->profesor);
        $res = $this->getJson("/api/entregas/{$this->tarea->id}")->assertOk();

        $porEstudiante = collect($res->json('data'))->keyBy('estudiante.id');
        expect($porEstudiante)->toHaveCount(2) // estudiante + compañero; el de otra sección no
            ->and($porEstudiante[$this->e->estudiante->id]['ha_entregado'])->toBeTrue()
            ->and($porEstudiante[$this->e->companero->id]['ha_entregado'])->toBeFalse()
            ->and($res->json('meta.resumen'))->toMatchArray([
                'total_estudiantes' => 2,
                'total_entregados' => 1,
                'total_faltan' => 1,
            ]);
    });

    test('un profesor ajeno NO ve las entregas', function () {
        Sanctum::actingAs($this->e->otroProfesor);
        $this->getJson("/api/entregas/{$this->tarea->id}")->assertStatus(403);
    });
});

describe('Descargar entrega', function () {

    test('la descarga solo la obtienen el profesor de la materia y el admin', function () {
        subirEntrega($this, $this->e->estudiante, $this->tarea)->assertStatus(201);
        $entrega = Entrega::first();

        foreach ([$this->e->profesor, $this->e->admin] as $permitido) {
            app('auth')->forgetGuards();
            Sanctum::actingAs($permitido);
            $this->getJson("/api/entregas/descargar/{$entrega->id}")
                ->assertOk()
                ->assertJsonStructure(['data' => ['download_url', 'filename']]);
        }

        foreach ([$this->e->otroProfesor, $this->e->companero] as $denegado) {
            app('auth')->forgetGuards();
            Sanctum::actingAs($denegado);
            $this->getJson("/api/entregas/descargar/{$entrega->id}")->assertStatus(403);
        }
    });
});

describe('Eliminar entrega', function () {

    test('el estudiante elimina su entrega no calificada y se borra el archivo', function () {
        subirEntrega($this, $this->e->estudiante, $this->tarea)->assertStatus(201);
        $entrega = Entrega::first();

        Sanctum::actingAs($this->e->estudiante);
        $this->deleteJson("/api/entregas/{$entrega->id}")->assertOk();

        expect(Entrega::count())->toBe(0);
        Storage::disk('local')->assertMissing($entrega->archivo);
    });

    test('NO se puede eliminar una entrega calificada', function () {
        subirEntrega($this, $this->e->estudiante, $this->tarea)->assertStatus(201);
        $entrega = Entrega::first();
        $entrega->update(['nota' => 9]);

        Sanctum::actingAs($this->e->estudiante);
        $this->deleteJson("/api/entregas/{$entrega->id}")->assertStatus(400);
        expect(Entrega::count())->toBe(1);
    });

    test('NO se puede eliminar una entrega hecha después de la fecha límite', function () {
        $vencida = crearTarea($this->e, ['fecha_limite' => now()->subDay()]);
        subirEntrega($this, $this->e->estudiante, $vencida)->assertStatus(201);

        Sanctum::actingAs($this->e->estudiante);
        $this->deleteJson('/api/entregas/' . Entrega::first()->id)->assertStatus(400);
    });

    test('un compañero NO puede eliminar la entrega de otro', function () {
        subirEntrega($this, $this->e->estudiante, $this->tarea)->assertStatus(201);

        Sanctum::actingAs($this->e->companero);
        $this->deleteJson('/api/entregas/' . Entrega::first()->id)->assertStatus(403);
    });
});

describe('Calificar entrega', function () {

    beforeEach(function () {
        subirEntrega($this, $this->e->estudiante, $this->tarea)->assertStatus(201);
        $this->entrega = Entrega::first();
    });

    test('el profesor de la materia califica con observaciones', function () {
        Sanctum::actingAs($this->e->profesor);

        $this->putJson("/api/entregas/calificar/{$this->entrega->id}", [
            'nota' => 8.5,
            'observaciones' => 'Buen trabajo & bien presentado',
        ])->assertOk()->assertJsonPath('message', 'Entrega calificada correctamente');

        $this->entrega->refresh();
        expect((float) $this->entrega->nota)->toBe(8.5)
            ->and($this->entrega->observaciones)->toBe('Buen trabajo & bien presentado');
    });

    test('la nota no puede superar el puntaje máximo de la tarea ni ser negativa', function () {
        Sanctum::actingAs($this->e->profesor);

        $this->putJson("/api/entregas/calificar/{$this->entrega->id}", ['nota' => 11])->assertStatus(422);
        $this->putJson("/api/entregas/calificar/{$this->entrega->id}", ['nota' => -1])->assertStatus(422);
        expect($this->entrega->fresh()->nota)->toBeNull();
    });

    test('se puede calificar sobre más de 100 si la tarea vale más de 100', function () {
        $tarea200 = crearTarea($this->e, ['puntaje_maximo' => 200]);
        subirEntrega($this, $this->e->companero, $tarea200)->assertStatus(201);
        $entrega = Entrega::where('tarea_id', $tarea200->id)->first();

        Sanctum::actingAs($this->e->profesor);
        $this->putJson("/api/entregas/calificar/{$entrega->id}", ['nota' => 150])->assertOk();
        expect((float) $entrega->fresh()->nota)->toBe(150.0);
    });

    test('un profesor ajeno o un estudiante NO pueden calificar', function () {
        Sanctum::actingAs($this->e->otroProfesor);
        $this->putJson("/api/entregas/calificar/{$this->entrega->id}", ['nota' => 10])->assertStatus(403);

        Sanctum::actingAs($this->e->estudiante);
        $this->putJson("/api/entregas/calificar/{$this->entrega->id}", ['nota' => 10])->assertStatus(403);

        expect($this->entrega->fresh()->nota)->toBeNull();
    });
});

describe('Calificar directo (estudiante sin entrega)', function () {

    test('califica a un estudiante que no entregó creando la entrega sin archivo', function () {
        Sanctum::actingAs($this->e->profesor);

        $this->postJson('/api/calificar-estudiante', [
            'tarea_id' => $this->tarea->id,
            'estudiante_id' => $this->e->companero->id,
            'nota' => 0,
            'observaciones' => 'No entregó',
        ])->assertOk()->assertJsonPath('message', 'Calificación guardada correctamente');

        $entrega = Entrega::where('estudiante_id', $this->e->companero->id)->first();
        expect($entrega->archivo)->toBeNull()
            ->and((float) $entrega->nota)->toBe(0.0);
    });

    test('si ya existe la entrega, actualiza la calificación', function () {
        subirEntrega($this, $this->e->estudiante, $this->tarea)->assertStatus(201);

        Sanctum::actingAs($this->e->profesor);
        $this->postJson('/api/calificar-estudiante', [
            'tarea_id' => $this->tarea->id,
            'estudiante_id' => $this->e->estudiante->id,
            'nota' => 7,
        ])->assertOk()->assertJsonPath('message', 'Calificación actualizada correctamente');

        expect(Entrega::count())->toBe(1)
            ->and((float) Entrega::first()->nota)->toBe(7.0);
    });

    test('un profesor ajeno recibe 403 (no un error 500)', function () {
        Sanctum::actingAs($this->e->otroProfesor);

        $this->postJson('/api/calificar-estudiante', [
            'tarea_id' => $this->tarea->id,
            'estudiante_id' => $this->e->estudiante->id,
            'nota' => 10,
        ])->assertStatus(403);

        expect(Entrega::count())->toBe(0);
    });

    test('NO se puede calificar a alguien que no es estudiante inscrito en la materia', function () {
        Sanctum::actingAs($this->e->profesor);

        foreach ([$this->e->ajeno, $this->e->admin, $this->e->otroProfesor] as $noInscrito) {
            $this->postJson('/api/calificar-estudiante', [
                'tarea_id' => $this->tarea->id,
                'estudiante_id' => $noInscrito->id,
                'nota' => 10,
            ])->assertStatus(422);
        }

        expect(Entrega::count())->toBe(0);
    });

    test('tarea_id, estudiante_id y nota son obligatorios', function () {
        Sanctum::actingAs($this->e->profesor);

        $this->postJson('/api/calificar-estudiante', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tarea_id', 'estudiante_id', 'nota']);
    });
});
