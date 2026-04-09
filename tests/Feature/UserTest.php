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
                'current_page',
                'last_page',
                'total',
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

        $userData = [
            'first_name' => 'Nuevo',
            'last_name' => 'Usuario',
            'email' => 'nuevo@ejemplo.com',
            'password' => 'password123',
            'identification_number' => '12345678901',
            'phone' => '+1234567890',
            'role_id' => $estudianteRole->id,
            'activo' => true,
        ];

        $response = $this->postJson('/api/users', $userData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'nuevo@ejemplo.com']);
    });

    test('admin puede actualizar usuario', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $userToUpdate = User::factory()->create();

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'activo' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/users/{$userToUpdate->id}", [
            'first_name' => 'Nombre Actualizado',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $userToUpdate->id,
            'first_name' => 'Nombre Actualizado',
        ]);
    });

    test('admin puede eliminar usuario', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $userToDelete = User::factory()->create();

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'activo' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/users/{$userToDelete->id}");

        $response->assertStatus(200);

        // Verificar que el usuario fue desactivado (activo = false)
        $this->assertDatabaseHas('users', [
            'id' => $userToDelete->id,
            'activo' => false,
        ]);
    });

    test('admin puede ver un usuario específico', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $userToView = User::factory()->create();

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'activo' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/users/{$userToView->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $userToView->id,
                'email' => $userToView->email,
            ]);
    });
});

describe('Validaciones de CRUD', function () {
    test('crear usuario con email duplicado falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $existingUser = User::factory()->create();
        
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'activo' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/users', [
            'first_name' => 'Test',
            'email' => $existingUser->email,
            'password' => 'password123',
            'role_id' => $adminRole->id,
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
            'email' => 'test@test.com',
            'password' => 'password123',
            'role_id' => 99999,
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
        
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'activo' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'current_page', 'last_page', 'total']);
    });

    test('respuesta de show tiene estructura correcta', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $user = User::factory()->create();
        
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'activo' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/users/{$user->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['id', 'first_name', 'last_name', 'email', 'role_id', 'activo']);
    });
});
