<?php

/**
 * Contrato de respuestas de la API (ver App\Traits\ApiResponse).
 *
 * Éxito (2xx):  { data, message?, meta? }      — siempre incluye "data"
 * Error (4xx):  { message, errors?, data? }    — nunca stack trace ni rutas internas
 *
 * Si alguien agrega un endpoint que responde con otro formato, esta prueba falla.
 */

use App\Models\ClassSchedule;
use App\Models\Modulo;
use App\Models\Parcial;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Tarea;
use App\Models\User;
use App\Services\ParcialService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    foreach (['admin', 'estudiante', 'profesor'] as $rol) {
        Role::firstOrCreate(['name' => $rol]);
    }

    $sy = SchoolYear::factory()->create(['active' => true]);
    $section = Section::factory()->create(['school_year_id' => $sy->id]);
    $subject = Subject::factory()->create();

    $this->users = [
        'admin' => User::factory()->admin()->create(['activo' => true]),
        'profesor' => User::factory()->profesor()->create(['activo' => true]),
        'estudiante' => User::factory()->estudiante()->create(['activo' => true, 'section_id' => $section->id]),
    ];

    $cs = ClassSchedule::factory()->create([
        'teacher_id' => $this->users['profesor']->id,
        'subject_id' => $subject->id,
        'section_id' => $section->id,
        'start_time' => '08:00',
        'end_time' => '09:00',
    ]);

    $modulo = Modulo::create(['nombre' => 'Módulo 1', 'materia_id' => $subject->id]);
    app(ParcialService::class)->asegurarParciales($modulo->id);
    $parcial = Parcial::first();
    $parametro = $parcial->parametros()->first();
    $tarea = Tarea::create([
        'titulo' => 'Tarea 1',
        'descripcion' => 'Descripción',
        'fecha_limite' => now()->addDay(),
        'modulo_id' => $modulo->id,
        'puntaje_maximo' => 10,
        'parcial_id' => $parcial->id,
        'parametro_id' => $parametro->id,
    ]);

    $est = $this->users['estudiante']->id;

    $this->exitos = [
        ['admin', '/api/users'], ['admin', '/api/users/count'], ['admin', "/api/users/{$est}"],
        ['admin', '/api/users/check-email?email=nuevo@correo.com'],
        ['admin', '/api/users/check-availability?field=email&value=nuevo@correo.com'],
        ['admin', '/api/roles'], ['admin', '/api/teachers'],
        ['admin', '/api/school-years'], ['admin', '/api/school-years/active'], ['admin', "/api/school-years/{$sy->id}"],
        ['admin', '/api/grades'], ['admin', '/api/sections'], ['admin', "/api/sections/{$section->id}"],
        ['admin', '/api/subjects'], ['admin', "/api/subjects/{$subject->id}"], ['admin', '/api/subjects?count_only=1'],
        ['admin', '/api/class-schedules'], ['admin', "/api/class-schedules/{$cs->id}"], ['admin', '/api/class-schedules/count'],
        ['admin', '/api/activity-logs'], ['admin', '/api/activity-logs/count'],
        ['admin', '/api/admin/dashboard/stats'], ['admin', '/api/admin/courses/pending-closure'],
        ['admin', '/api/admin/enrollments/available-sections'],
        ['admin', "/api/admin/students/{$est}/academic-history"],
        ['admin', "/api/admin/students/{$est}/promotion-eligibility"],
        ['admin', "/api/sections/{$section->id}/schedule"], ['admin', '/api/me'],
        ['profesor', '/api/my-subjects'], ['profesor', '/api/my-schedule'],
        ['profesor', '/api/teacher/dashboard'], ['profesor', '/api/teacher/courses/stats'],
        ['profesor', "/api/materias/{$subject->id}/modulos"], ['profesor', "/api/modulos/{$modulo->id}/parciales"],
        ['profesor', "/api/parciales/{$parcial->id}/parametros"], ['profesor', "/api/modulos/{$modulo->id}/tareas"],
        ['profesor', "/api/tareas/{$tarea->id}"], ['profesor', "/api/modulos/{$modulo->id}/notas/resumen"],
        ['profesor', "/api/entregas/{$tarea->id}"], ['profesor', "/api/subjects/{$subject->id}/participantes"],
        ['profesor', "/api/subjects/{$subject->id}/participantes?page=1"],
        ['profesor', "/api/subjects/{$subject->id}/sections/{$section->id}/status"],
        ['estudiante', '/api/my-subjects-student'], ['estudiante', '/api/my-schedule'],
        ['estudiante', "/api/modulos/{$modulo->id}/notas/mis-notas"], ['estudiante', "/api/entregas/{$tarea->id}"],
        ['estudiante', "/api/students/{$est}/courses"],
    ];

    $this->errores = [
        ['admin', '/api/users/999999', 404], ['estudiante', '/api/users', 403], [null, '/api/me', 401],
        ['profesor', '/api/tareas/999999', 404], ['profesor', '/api/materias/999999/modulos', 404],
        ['admin', '/api/ruta-que-no-existe', 404], ['estudiante', "/api/entregas/{$tarea->id}/mi-entrega", 404],
    ];
});

function verificarExito($test, array $json, string $url): void
{
    $test->assertIsArray($json, "$url no devolvió un objeto JSON");
    $test->assertArrayHasKey('data', $json, "$url no tiene la clave 'data'");
    $extra = array_diff(array_keys($json), ['data', 'message', 'meta']);
    $test->assertEmpty($extra, "$url tiene claves fuera del contrato: " . implode(', ', $extra));
}

test('las respuestas exitosas siguen el formato { data, message?, meta? }', function () {
    foreach ($this->exitos as [$rol, $url]) {
        app('auth')->forgetGuards();
        Sanctum::actingAs($this->users[$rol]);

        $res = $this->getJson($url);
        expect($res->status())->toBeGreaterThanOrEqual(200)->toBeLessThan(300, "$url respondió {$res->status()}");
        verificarExito($this, $res->json(), $url);
    }
});

test('las respuestas de error siguen el formato { message, errors?, data? } sin detalles internos', function () {
    foreach ($this->errores as [$rol, $url, $status]) {
        app('auth')->forgetGuards();
        if ($rol) {
            Sanctum::actingAs($this->users[$rol]);
        }

        $res = $this->getJson($url)->assertStatus($status);
        $json = $res->json();

        $this->assertArrayHasKey('message', $json, "$url no tiene 'message'");
        $extra = array_diff(array_keys($json), ['message', 'errors', 'data']);
        $this->assertEmpty($extra, "$url expone claves no permitidas: " . implode(', ', $extra));
    }
});

test('un ID inexistente responde "Recurso no encontrado" en español y sin trace', function () {
    Sanctum::actingAs($this->users['admin']);
    $this->getJson('/api/users/999999')
        ->assertStatus(404)
        ->assertExactJson(['message' => 'Recurso no encontrado']);
});

test('el 403 por rol no revela qué rol se necesita', function () {
    Sanctum::actingAs($this->users['estudiante']);
    $this->getJson('/api/users')
        ->assertStatus(403)
        ->assertJsonMissingPath('required_roles')
        ->assertJsonMissingPath('current_role');
});

test('los errores de validación usan { message, errors }', function () {
    Sanctum::actingAs($this->users['admin']);
    $this->postJson('/api/subjects', [])
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors']);
});

test('login devuelve { message, data: { token, token_type, user } }', function () {
    $this->users['admin']->update(['password' => bcrypt('clave-segura')]);

    $this->postJson('/api/login', ['email' => $this->users['admin']->email, 'password' => 'clave-segura'])
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['token', 'token_type', 'user' => ['id', 'email', 'role']]]);
});

test('crear y editar devuelven { data, message }', function () {
    Sanctum::actingAs($this->users['admin']);

    $id = $this->postJson('/api/subjects', ['name' => 'Física'])
        ->assertStatus(201)
        ->assertJsonStructure(['data' => ['id'], 'message'])
        ->json('data.id');

    $this->putJson("/api/subjects/{$id}", ['name' => 'Física I'])
        ->assertOk()
        ->assertJsonStructure(['data', 'message']);
});
