<?php

/**
 * Pruebas de seguridad del aula virtual: archivos privados, selfie,
 * permisos sobre módulos / materiales / parciales y login.
 */

use App\Models\ClassSchedule;
use App\Models\Material;
use App\Models\Modulo;
use App\Models\Parcial;
use App\Models\Role;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Support\ArchivoPrivado;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'estudiante']);
    Role::firstOrCreate(['name' => 'profesor']);

    Storage::fake('local');
    Storage::fake('public');

    // Escenario: una materia dictada por "profesor" en una sección con un estudiante.
    $this->section = Section::factory()->create();
    $this->subject = Subject::factory()->create();
    $this->profesor = User::factory()->profesor()->create(['activo' => true]);
    $this->otroProfesor = User::factory()->profesor()->create(['activo' => true]);
    $this->estudiante = User::factory()->estudiante()->create([
        'activo' => true,
        'section_id' => $this->section->id,
    ]);

    ClassSchedule::factory()->create([
        'teacher_id' => $this->profesor->id,
        'subject_id' => $this->subject->id,
        'section_id' => $this->section->id,
        'start_time' => '08:00',
        'end_time' => '09:00',
    ]);

    $this->modulo = Modulo::create([
        'nombre' => 'Módulo 1',
        'materia_id' => $this->subject->id,
    ]);
});

function subirMaterial($test, User $user)
{
    Sanctum::actingAs($user);
    return $test->post("/api/modulos/{$test->modulo->id}/materiales", [
        'archivo' => UploadedFile::fake()->create('clase1.pdf', 100, 'application/pdf'),
    ], ['Accept' => 'application/json']);
}

describe('Archivos privados (materiales, tareas, entregas)', function () {

    test('el material se guarda en el disco privado, no en el público', function () {
        subirMaterial($this, $this->profesor)->assertStatus(201);

        $material = Material::first();
        expect($material->ruta)->toStartWith('materiales/');
        Storage::disk('local')->assertExists($material->ruta);
        Storage::disk('public')->assertMissing($material->ruta);
    });

    test('estudiante inscrito obtiene una URL firmada que sí descarga el archivo', function () {
        subirMaterial($this, $this->profesor)->assertStatus(201);
        $material = Material::first();

        Sanctum::actingAs($this->estudiante);
        $url = $this->getJson("/api/materiales/{$material->id}/descargar")
            ->assertOk()
            ->json('data.download_url');

        expect($url)->toContain('/api/archivos')->toContain('signature=');

        // La URL firmada funciona sin token (p. ej. en una pestaña nueva)
        app('auth')->forgetGuards();
        $this->get($url)->assertOk();
    });

    test('URL sin firma o con la ruta modificada es rechazada', function () {
        subirMaterial($this, $this->profesor)->assertStatus(201);
        $material = Material::first();

        $this->get('/api/archivos?ruta=' . urlencode($material->ruta))->assertStatus(403);

        $firmada = ArchivoPrivado::url($material->ruta);
        $alterada = str_replace(urlencode($material->ruta), urlencode('entregas/otro.pdf'), $firmada);
        $this->get($alterada)->assertStatus(403);
    });

    test('URL firmada vencida es rechazada', function () {
        subirMaterial($this, $this->profesor)->assertStatus(201);
        $url = ArchivoPrivado::url(Material::first()->ruta);

        $this->travel(ArchivoPrivado::MINUTOS_VALIDEZ + 1)->minutes();
        $this->get($url)->assertStatus(403);
    });

    test('no se pueden pedir archivos fuera de las carpetas permitidas', function () {
        expect(ArchivoPrivado::rutaPermitida('materiales/a.pdf'))->toBeTrue()
            ->and(ArchivoPrivado::rutaPermitida('../.env'))->toBeFalse()
            ->and(ArchivoPrivado::rutaPermitida('materiales/../../.env'))->toBeFalse()
            ->and(ArchivoPrivado::rutaPermitida('/etc/passwd'))->toBeFalse()
            ->and(ArchivoPrivado::rutaPermitida('otra/a.pdf'))->toBeFalse();

        $this->get(ArchivoPrivado::url('../.env'))->assertStatus(404);
    });

    test('archivos antiguos en el disco público se siguen encontrando', function () {
        Storage::disk('public')->put('materiales/viejo.pdf', 'contenido');
        expect(ArchivoPrivado::disco('materiales/viejo.pdf'))->toBe('public');
        $this->get(ArchivoPrivado::url('materiales/viejo.pdf'))->assertOk();
    });

    test('comando archivos:privatizar mueve los archivos al disco privado', function () {
        Storage::disk('public')->put('entregas/e1.pdf', 'x');
        Storage::disk('public')->put('tareas/t1.pdf', 'y');

        $this->artisan('archivos:privatizar')->assertSuccessful();

        Storage::disk('local')->assertExists(['entregas/e1.pdf', 'tareas/t1.pdf']);
        Storage::disk('public')->assertMissing(['entregas/e1.pdf', 'tareas/t1.pdf']);
    });
});

describe('Permisos sobre materiales', function () {

    test('un profesor que no dicta la materia NO puede subir material', function () {
        subirMaterial($this, $this->otroProfesor)->assertStatus(403);
        expect(Material::count())->toBe(0);
    });

    test('un profesor que no dicta la materia NO puede borrar material', function () {
        subirMaterial($this, $this->profesor)->assertStatus(201);
        $material = Material::first();

        Sanctum::actingAs($this->otroProfesor);
        $this->deleteJson("/api/materiales/{$material->id}")->assertStatus(403);
        expect(Material::count())->toBe(1);
    });

    test('el profesor de la materia sí puede borrar su material', function () {
        subirMaterial($this, $this->profesor)->assertStatus(201);
        $material = Material::first();

        Sanctum::actingAs($this->profesor);
        $this->deleteJson("/api/materiales/{$material->id}")->assertOk();
        Storage::disk('local')->assertMissing($material->ruta);
    });
});

describe('Permisos sobre parciales y notas', function () {

    test('un estudiante NO puede forzar la creación de parciales', function () {
        Sanctum::actingAs($this->estudiante);
        $this->postJson("/api/modulos/{$this->modulo->id}/parciales/crear")->assertStatus(403);
        expect(Parcial::count())->toBe(0);
    });

    test('el profesor de la materia sí puede forzar la creación de parciales', function () {
        Sanctum::actingAs($this->profesor);
        $this->postJson("/api/modulos/{$this->modulo->id}/parciales/crear")->assertOk();
        expect(Parcial::where('modulo_id', $this->modulo->id)->count())->toBe(2);
    });

    test('un profesor NO puede ver el resumen de notas de una materia que no dicta', function () {
        Sanctum::actingAs($this->otroProfesor);
        $this->getJson("/api/modulos/{$this->modulo->id}/notas/resumen")->assertStatus(403);
    });

    test('el profesor de la materia sí puede ver el resumen de notas', function () {
        Sanctum::actingAs($this->profesor);
        $this->getJson("/api/modulos/{$this->modulo->id}/notas/resumen")->assertOk();
    });

    test('un profesor NO puede modificar parámetros de una materia que no dicta', function () {
        Sanctum::actingAs($this->profesor);
        $this->postJson("/api/modulos/{$this->modulo->id}/parciales/crear")->assertOk();
        $parametro = Parcial::first()->parametros()->first();

        Sanctum::actingAs($this->otroProfesor);
        $this->putJson("/api/parametros/{$parametro->id}", ['porcentaje' => 5])->assertStatus(403);
    });

    test('un estudiante de otra sección NO puede ver los parciales del módulo', function () {
        $ajeno = User::factory()->estudiante()->create([
            'activo' => true,
            'section_id' => Section::factory()->create()->id,
        ]);
        Sanctum::actingAs($ajeno);
        $this->getJson("/api/modulos/{$this->modulo->id}/parciales")->assertStatus(403);

        Sanctum::actingAs($this->estudiante);
        $this->getJson("/api/modulos/{$this->modulo->id}/parciales")->assertOk();
    });
});

describe('Selfie', function () {

    test('se rechaza HTML disfrazado de imagen (XSS almacenado)', function () {
        Sanctum::actingAs($this->estudiante);
        $this->postJson('/api/profile/selfie', [
            'selfie_data' => base64_encode('<script>alert(1)</script>'),
            'mime_type' => 'text/html',
        ])->assertStatus(422);

        expect($this->estudiante->fresh()->selfie)->toBeNull();
    });

    test('una imagen PNG válida se guarda y se devuelve con su tipo real', function () {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        Sanctum::actingAs($this->estudiante);
        $this->postJson('/api/profile/selfie', [
            'selfie_data' => 'data:image/png;base64,' . base64_encode($png),
            'mime_type' => 'text/html', // el valor del cliente se ignora
        ])->assertOk();

        // Recargar el usuario desde la BD (la selfie se guardó con PDO directo)
        Sanctum::actingAs($this->estudiante->fresh());
        $this->get('/api/profile/selfie')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    });
});
