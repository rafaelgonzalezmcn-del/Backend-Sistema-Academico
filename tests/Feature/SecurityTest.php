<?php

use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'estudiante']);
    Role::firstOrCreate(['name' => 'profesor']);
});

describe('Seguridad - Restricciones de acceso por rol', function () {
    
    test('profesor NO puede listar usuarios', function () {
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        User::factory()->count(3)->create();
        Sanctum::actingAs($profesor);
        $response = $this->getJson('/api/users');
        $response->assertStatus(403);
    });

    test('profesor NO puede crear usuarios', function () {
        $profesorRole = Role::where('name', 'profesor')->first();
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        Sanctum::actingAs($profesor);
        $userData = ['first_name' => 'Nuevo', 'email' => 'nuevo@test.com', 'password' => 'password123', 'role_id' => $estudianteRole->id, 'activo' => true];
        $response = $this->postJson('/api/users', $userData);
        $response->assertStatus(403);
    });

    test('profesor NO puede actualizar usuarios', function () {
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        $userToUpdate = User::factory()->create();
        Sanctum::actingAs($profesor);
        $response = $this->putJson("/api/users/{$userToUpdate->id}", ['first_name' => 'Actualizado']);
        $response->assertStatus(403);
    });

    test('profesor NO puede eliminar usuarios', function () {
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        $userToDelete = User::factory()->create();
        Sanctum::actingAs($profesor);
        $response = $this->deleteJson("/api/users/{$userToDelete->id}");
        $response->assertStatus(403);
    });

    test('estudiante NO puede listar usuarios', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true]);
        Sanctum::actingAs($estudiante);
        $response = $this->getJson('/api/users');
        $response->assertStatus(403);
    });

    test('estudiante NO puede crear usuarios', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true]);
        Sanctum::actingAs($estudiante);
        $userData = ['first_name' => 'Nuevo', 'email' => 'nuevo@test.com', 'password' => 'password123', 'role_id' => $estudianteRole->id, 'activo' => true];
        $response = $this->postJson('/api/users', $userData);
        $response->assertStatus(403);
    });

    test('estudiante NO puede actualizar usuarios', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true]);
        $userToUpdate = User::factory()->create();
        Sanctum::actingAs($estudiante);
        $response = $this->putJson("/api/users/{$userToUpdate->id}", ['first_name' => 'Actualizado']);
        $response->assertStatus(403);
    });

    test('estudiante NO puede eliminar usuarios', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true]);
        $userToDelete = User::factory()->create();
        Sanctum::actingAs($estudiante);
        $response = $this->deleteJson("/api/users/{$userToDelete->id}");
        $response->assertStatus(403);
    });
});

describe('Seguridad - Acceso sin token', function () {
    
    test('usuario sin token NO puede listar usuarios', function () {
        $response = $this->getJson('/api/users');
        $response->assertStatus(401);
    });

    test('usuario sin token NO puede crear usuarios', function () {
        $role = Role::where('name', 'estudiante')->first();
        $response = $this->postJson('/api/users', ['first_name' => 'Nuevo', 'email' => 'test@test.com', 'password' => 'password123', 'role_id' => $role->id, 'activo' => true]);
        $response->assertStatus(401);
    });

    test('usuario sin token NO puede actualizar usuarios', function () {
        $user = User::factory()->create();
        $response = $this->putJson("/api/users/{$user->id}", ['first_name' => 'Actualizado']);
        $response->assertStatus(401);
    });

    test('usuario sin token NO puede eliminar usuarios', function () {
        $user = User::factory()->create();
        $response = $this->deleteJson("/api/users/{$user->id}");
        $response->assertStatus(401);
    });

    test('usuario sin token NO puede acceder a /me', function () {
        $response = $this->getJson('/api/me');
        $response->assertStatus(401);
    });
});

describe('Seguridad - Casos adicionales', function () {
    
    test('profesor puede acceder a /me con su token', function () {
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        $token = $profesor->createToken('test-token')->plainTextToken;
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->getJson('/api/me');
        $response->assertStatus(200)->assertJsonPath('id', $profesor->id);
    });

    test('estudiante puede acceder a /me con su token', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true]);
        $token = $estudiante->createToken('test-token')->plainTextToken;
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->getJson('/api/me');
        $response->assertStatus(200)->assertJsonPath('id', $estudiante->id);
    });

    test('profesor no puede acceder a ruta de admin sin permisos', function () {
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        Sanctum::actingAs($profesor);
        $response = $this->getJson('/api/users');
        $response->assertStatus(403);
    });

    test('estudiante no puede acceder a ruta de admin sin permisos', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true]);
        Sanctum::actingAs($estudiante);
        $response = $this->getJson('/api/users');
        $response->assertStatus(403);
    });

    test('token de usuario inactivo es rechazado', function () {
        $role = Role::where('name', 'admin')->first();
        $user = User::factory()->create(['role_id' => $role->id, 'activo' => false]);
        Sanctum::actingAs($user);
        $response = $this->getJson('/api/me');
        $response->assertStatus(403);
    });
});
