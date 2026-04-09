<?php

use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'estudiante']);
    Role::firstOrCreate(['name' => 'profesor']);
});

describe('CRUD Subjects - Admin', function () {
    test('admin puede listar materias', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        Subject::factory()->count(3)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/subjects');

        $response->assertStatus(200);
        $response->assertJsonCount(3);
    });

    test('admin puede crear materia', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        Sanctum::actingAs($admin);

        $subjectData = [
            'name' => 'Matemáticas Avanzadas',
        ];

        $response = $this->postJson('/api/subjects', $subjectData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('subjects', ['name' => 'Matemáticas Avanzadas']);
    });

    test('admin puede actualizar materia', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $subject = Subject::factory()->create(['name' => 'Vieja Materia']);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/subjects/{$subject->id}", [
            'name' => 'Nueva Materia'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'name' => 'Nueva Materia']);
    });

    test('admin puede eliminar materia', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $subject = Subject::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/subjects/{$subject->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
    });
});

describe('Validaciones Subject', function () {
    test('crear materia sin nombre falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/subjects', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    test('crear materia con nombre duplicado falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $existingSubject = Subject::factory()->create(['name' => 'Duplicada']);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/subjects', [
            'name' => 'Duplicada'
        ]);

        $response->assertStatus(422);
    });

    test('profesor no puede crear materia', function () {
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);

        Sanctum::actingAs($profesor);

        $response = $this->postJson('/api/subjects', [
            'name' => 'Nueva Materia'
        ]);

        $response->assertStatus(403);
    });

    test('estudiante no puede crear materia', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true]);

        Sanctum::actingAs($estudiante);

        $response = $this->postJson('/api/subjects', [
            'name' => 'Nueva Materia'
        ]);

        $response->assertStatus(403);
    });

    test('estudiante puede listar materias', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true]);

        Subject::factory()->count(3)->create();

        Sanctum::actingAs($estudiante);

        $response = $this->getJson('/api/subjects');

        $response->assertStatus(200);
        $response->assertJsonCount(3);
    });

    test('profesor puede listar materias', function () {
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);

        Subject::factory()->count(3)->create();

        Sanctum::actingAs($profesor);

        $response = $this->getJson('/api/subjects');

        $response->assertStatus(200);
        $response->assertJsonCount(3);
    });
});
