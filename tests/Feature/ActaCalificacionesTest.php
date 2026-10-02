<?php

/**
 * Acta de calificaciones (PDF y Excel):
 * permisos, formatos, datos provisionales vs. oficiales.
 */

use App\Models\Entrega;
use App\Models\Subject;
use App\Services\ActaService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->e = escenarioAcademico();
    $this->e->parcial->update(['nota_maxima' => 10]);
    $this->pExamenes = $this->e->parcial->parametros()->where('tipo', 'examenes')->first();
    $this->pExamenes->update(['porcentaje' => 100]);
    $this->e->estudiante->update(['first_name' => 'Zoe', 'last_name' => 'Zambrano']);
    $this->e->companero->update(['first_name' => 'José', 'last_name' => 'Álvarez']);

    $this->tarea = crearTarea($this->e, ['parametro_id' => $this->pExamenes->id, 'puntaje_maximo' => 10]);
    Entrega::create(['tarea_id' => $this->tarea->id, 'estudiante_id' => $this->e->estudiante->id, 'fecha_entrega' => now(), 'nota' => 9]);
    Entrega::create(['tarea_id' => $this->tarea->id, 'estudiante_id' => $this->e->companero->id, 'fecha_entrega' => now(), 'nota' => 5]);

    $this->url = "/api/subjects/{$this->e->materia->id}/sections/{$this->e->seccionA->id}/acta";
});

describe('Permisos', function () {

    test('el profesor del curso y el admin pueden descargarla', function () {
        foreach ([$this->e->profesor, $this->e->admin] as $permitido) {
            app('auth')->forgetGuards();
            Sanctum::actingAs($permitido);
            $this->get($this->url)->assertOk();
        }
    });

    test('un profesor ajeno o un estudiante NO pueden', function () {
        foreach ([$this->e->otroProfesor, $this->e->estudiante] as $sinPermiso) {
            app('auth')->forgetGuards();
            Sanctum::actingAs($sinPermiso);
            $this->getJson($this->url)->assertStatus(403);
        }
    });

    test('sin sesión responde 401', function () {
        $this->getJson($this->url)->assertStatus(401);
    });
});

describe('Formatos', function () {

    test('PDF por defecto, como archivo descargable', function () {
        Sanctum::actingAs($this->e->profesor);
        $res = $this->get($this->url)->assertOk()->assertHeader('Content-Type', 'application/pdf');

        expect(substr($res->getContent(), 0, 5))->toBe('%PDF-')
            ->and($res->headers->get('Content-Disposition'))->toContain('attachment')->toContain('-provisional.pdf');
    });

    test('Excel (.xlsx) válido', function () {
        Sanctum::actingAs($this->e->profesor);
        $res = $this->get("{$this->url}?formato=xlsx")->assertOk();

        $ruta = $res->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive();
        expect($zip->open($ruta))->toBeTrue()
            ->and($zip->locateName('xl/worksheets/sheet1.xml'))->not->toBeFalse()
            ->and($res->headers->get('Content-Disposition'))->toContain('.xlsx');

        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml') . $zip->getFromName('xl/sharedStrings.xml');
        expect($hoja)->toContain('ACTA DE CALIFICACIONES')->toContain('Álvarez');
        $zip->close();
    });

    test('formato inválido responde 422', function () {
        Sanctum::actingAs($this->e->profesor);
        $this->getJson("{$this->url}?formato=docx")->assertStatus(422)->assertJsonValidationErrors('formato');
    });

    test('una materia que no se dicta en la sección responde 422', function () {
        $otraMateria = Subject::factory()->create();
        Sanctum::actingAs($this->e->admin);
        $this->getJson("/api/subjects/{$otraMateria->id}/sections/{$this->e->seccionA->id}/acta")->assertStatus(422);
    });
});

describe('Contenido', function () {

    test('curso abierto: acta provisional con el mismo cálculo que Mis notas', function () {
        $acta = app(ActaService::class)->generar($this->e->materia, $this->e->seccionA);

        expect($acta['cerrado'])->toBeFalse()
            ->and($acta['parciales'])->toHaveCount(1) // el Parcial 2 sin tareas no aparece
            ->and($acta['nota_maxima_total'])->toEqual(10.0)
            ->and($acta['filas'])->toHaveCount(2);

        // Orden alfabético ignorando tildes: Álvarez antes que Zambrano
        [$alvarez, $zambrano] = $acta['filas'];
        expect($alvarez['estudiante'])->toBe('Álvarez José')
            ->and($alvarez['numero'])->toBe(1)
            ->and($alvarez['total'])->toEqual(5.0)
            ->and($alvarez['estado'])->toBe('reprobado')
            ->and($zambrano['total'])->toEqual(9.0)
            ->and($zambrano['porcentaje'])->toEqual(90.0)
            ->and($zambrano['estado'])->toBe('aprobado')
            ->and($acta['resumen'])->toMatchArray(['total' => 2, 'aprobados' => 1, 'reprobados' => 1, 'sin_calificaciones' => 0]);
    });

    test('curso cerrado: usa las notas oficiales aunque después cambien las entregas', function () {
        Sanctum::actingAs($this->e->profesor);
        $this->postJson("/api/subjects/{$this->e->materia->id}/sections/{$this->e->seccionA->id}/close")->assertOk();

        // Cambio posterior al cierre: no debe alterar el acta oficial
        Entrega::where('estudiante_id', $this->e->companero->id)->update(['nota' => 10]);

        $acta = app(ActaService::class)->generar($this->e->materia, $this->e->seccionA);
        $alvarez = collect($acta['filas'])->firstWhere('estudiante', 'Álvarez José');

        expect($acta['cerrado'])->toBeTrue()
            ->and($acta['fecha_cierre'])->not->toBeNull()
            ->and($alvarez['total'])->toEqual(5.0)
            ->and($alvarez['estado'])->toBe('reprobado');

        $disposicion = $this->get($this->url)->assertOk()->headers->get('Content-Disposition');
        expect($disposicion)->not->toContain('provisional');
    });

    test('incluye docente, grado, sección y año lectivo', function () {
        $acta = app(ActaService::class)->generar($this->e->materia, $this->e->seccionA);

        expect($acta['profesor'])->toBe(trim($this->e->profesor->first_name . ' ' . $this->e->profesor->last_name))
            ->and($acta['grado'])->toBe($this->e->seccionA->grade->name)
            ->and($acta['seccion'])->toBe($this->e->seccionA->name)
            ->and($acta['anio_lectivo'])->toBe($this->e->seccionA->schoolYear->name)
            ->and($acta['porcentaje_aprobacion'])->toEqual(70.0);
    });
});
