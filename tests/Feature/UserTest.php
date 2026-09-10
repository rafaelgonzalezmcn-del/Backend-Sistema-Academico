<?php

use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'estudiante']);
    Role::firstOrCreate(['name' => 'profesor']);
});

describe('CRUD Usuarios - Admin puede realizar operaciones', function () {
    
    test('admin puede listar usuarios', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'activo' => true,
        ]);

        User::factory()->count(3)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'first_name',
                        'last_name',
                        'email',
                        'role_id',
                        'activo',
                    ],
                ],
                'meta' => ['current_page', 'last_page', 'total'],
            ]);
    });

    test('admin puede crear usuario', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'activo' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/users', [
            'first_name' => 'Nuevo',
            'last_name' => 'Usuario',
            'email' => 'nuevo@test.com',
            'password' => 'password123',
            'role_id' => $estudianteRole->id,
            'identification' => '12345678',
        ]);

        $response->assertStatus(201);
    });

    test('admin puede actualizar usuario', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'activo' => true,
        ]);
        
        $user = User::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/users/{$user->id}", [
            'first_name' => 'Actualizado',
        ]);

        $response->assertStatus(200);
    });

    test('admin puede eliminar usuario', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'activo' => true,
        ]);
        
        $user = User::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/users/{$user->id}");

        $response->assertStatus(200);
    });

    test('admin puede ver un usuario específico', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'activo' => true,
        ]);
        
        $user = User::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/users/{$user->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $user->id);
    });
});

describe('Validaciones de CRUD', function () {
    test('crear usuario con email duplicado falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'activo' => true,
        ]);
        
        $existingUser = User::factory()->create(['email' => 'existente@test.com']);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/users', [
            'first_name' => 'Nuevo',
            'last_name' => 'Usuario',
            'email' => 'existente@test.com',
            'password' => 'password123',
            'role_id' => $adminRole->id,
            'identification' => '87654321',
        ]);

        $response->assertStatus(422);
    });

    test('crear usuario sin campos requeridos falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'activo' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/users', []);

        $response->assertStatus(422);
    });

    test('crear usuario con role_id inexistente falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'activo' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/users', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@test.com',
            'password' => 'password123',
            'role_id' => 99999,
            'identification' => '11111111',
        ]);

        $response->assertStatus(422);
    });

    test('actualizar usuario inexistente falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'activo' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/users/99999', [
            'first_name' => 'Test',
        ]);

        $response->assertStatus(404);
    });

    test('eliminar usuario inexistente falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'activo' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson('/api/users/99999');

        $response->assertStatus(404);
    });
});

describe('Estructura JSON de respuestas', function () {
    test('respuesta de índice tiene estructura correcta', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']]);
    });

    test('respuesta de show tiene estructura correcta', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $user = User::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/users/{$user->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $user->id);
    });
});