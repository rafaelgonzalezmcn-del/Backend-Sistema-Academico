<?php

/**
 * Correcciones de la segunda revisión:
 * datos públicos, límite de paginación, horario del estudiante,
 * cierre de sesiones y auto-desactivación del admin.
 */

use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->e = escenarioAcademico();
});

describe('Catálogos y horarios requieren sesión', function () {

    test('sin iniciar sesión responden 401', function () {
        foreach ([
            '/api/school-years', '/api/school-years/active', '/api/grades', '/api/sections',
            "/api/sections/{$this->e->seccionA->id}",
            "/api/sections/{$this->e->seccionA->id}/schedule",
            "/api/teachers/{$this->e->profesor->id}/schedule",
            "/api/students/{$this->e->seccionA->id}/schedule",
        ] as $url) {
            $this->getJson($url)->assertStatus(401);
        }
    });

    test('la lista de estudiantes de una sección solo la ven el admin y su profesor', function () {
        $url = "/api/sections/{$this->e->seccionA->id}";

        foreach ([$this->e->admin, $this->e->profesor] as $permitido) {
            app('auth')->forgetGuards();
            Sanctum::actingAs($permitido);
            expect($this->getJson($url)->assertOk()->json('data.students'))->toHaveCount(2);
        }

        foreach ([$this->e->otroProfesor, $this->e->estudiante, $this->e->ajeno] as $sinPermiso) {
            app('auth')->forgetGuards();
            Sanctum::actingAs($sinPermiso);
            $this->getJson($url)->assertOk()->assertJsonMissingPath('data.students');
        }
    });
});

describe('Límite de paginación', function () {

    test('per_page se limita a 100 y -1 ya no devuelve todo', function () {
        Section::factory()->count(105)->create();
        Sanctum::actingAs($this->e->admin);

        expect($this->getJson('/api/sections?per_page=100000')->json('meta.per_page'))->toBe(100)
            ->and($this->getJson('/api/sections?per_page=-1')->json('meta.per_page'))->toBe(15)
            ->and($this->getJson('/api/sections?per_page=100')->json('data'))->toHaveCount(100);

        User::factory()->count(5)->create();
        expect($this->getJson('/api/users?per_page=-1')->json('meta.per_page'))->toBe(15);
    });
});

describe('Horario del estudiante', function () {

    test('las horas llegan como HH:mm locales (no en UTC)', function () {
        Sanctum::actingAs($this->e->estudiante);

        $clases = collect($this->getJson('/api/my-schedule')->assertOk()->json('data'))->flatten(1);
        expect($clases->first()['start_time'])->toBe('08:00')
            ->and($clases->first()['end_time'])->toBe('09:00');
    });
});

describe('Sesiones abiertas', function () {

    test('al cambiar mi contraseña se cierran mis otras sesiones, pero no la actual', function () {
        $user = $this->e->estudiante;
        $user->update(['password' => Hash::make('clave-anterior')]);
        $actual = $user->createToken('este-dispositivo');
        $user->createToken('otro-dispositivo');

        $this->withToken($actual->plainTextToken)->putJson('/api/profile', [
            'first_name' => $user->first_name,
            'email' => $user->email,
            'current_password' => 'clave-anterior',
            'new_password' => 'clave-nueva-123',
            'new_password_confirmation' => 'clave-nueva-123',
        ])->assertOk();

        expect($user->tokens()->pluck('id')->all())->toBe([$actual->accessToken->id]);
    });

    test('desactivar un usuario cierra todas sus sesiones', function () {
        $this->e->estudiante->createToken('celular');
        Sanctum::actingAs($this->e->admin);

        $this->deleteJson("/api/users/{$this->e->estudiante->id}")->assertOk();
        expect($this->e->estudiante->tokens()->count())->toBe(0);
    });

    test('si el admin cambia la contraseña de un usuario, se cierran sus sesiones', function () {
        $this->e->profesor->createToken('laptop');
        Sanctum::actingAs($this->e->admin);

        $this->putJson("/api/users/{$this->e->profesor->id}", [
            'first_name' => $this->e->profesor->first_name,
            'email' => $this->e->profesor->email,
            'role_id' => $this->e->profesor->role_id,
            'password' => 'otra-clave-123',
        ])->assertOk();

        expect($this->e->profesor->tokens()->count())->toBe(0);
    });
});

describe('El admin no puede desactivarse a sí mismo', function () {

    test('ni con DELETE ni editando su propio usuario', function () {
        Sanctum::actingAs($this->e->admin);

        $this->deleteJson("/api/users/{$this->e->admin->id}")->assertStatus(403);
        $this->putJson("/api/users/{$this->e->admin->id}", [
            'first_name' => $this->e->admin->first_name,
            'email' => $this->e->admin->email,
            'role_id' => $this->e->admin->role_id,
            'activo' => false,
        ])->assertStatus(422);

        expect($this->e->admin->fresh()->activo)->toBeTrue();
    });
});
