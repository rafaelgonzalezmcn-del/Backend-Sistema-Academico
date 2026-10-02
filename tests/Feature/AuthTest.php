<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'estudiante']);
    Role::firstOrCreate(['name' => 'profesor']);
});

describe('Autenticación', function () {
    
    test('login exitoso devuelve token y datos del usuario', function () {
        $role = Role::where('name', 'admin')->first();
        
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
            'activo' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'first_name',
                        'last_name',
                        'email',
                        'role_id',
                        'activo',
                    ],
                    'token',
                    'token_type',
                ],
            ])
            ->assertJsonPath('data.user.email', 'test@example.com');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    });

    test('login con contraseña incorrecta devuelve error 401', function () {
        $role = Role::where('name', 'admin')->first();
        
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct_password'),
            'role_id' => $role->id,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Credenciales inválidas',
            ]);
    });

    test('login con email inexistente devuelve el mismo 401 que contraseña incorrecta', function () {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'any_password',
        ]);

        // No debe revelar si el correo existe (evita enumeración de usuarios)
        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Credenciales inválidas',
            ]);
    });

    test('usuario inactivo con contraseña incorrecta no revela que está desactivado', function () {
        $role = Role::where('name', 'admin')->first();

        User::factory()->create([
            'email' => 'inactive2@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
            'activo' => false,
        ]);

        $this->postJson('/api/login', [
            'email' => 'inactive2@example.com',
            'password' => 'otra_clave',
        ])->assertStatus(401)->assertJson(['message' => 'Credenciales inválidas']);
    });

    test('login con usuario inactivo devuelve error 403', function () {
        $role = Role::where('name', 'admin')->first();
        
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
            'activo' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Usuario desactivado',
            ]);
    });

    test('login sin email devuelve error de validación', function () {
        $response = $this->postJson('/api/login', [
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('login sin password devuelve error de validación', function () {
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });
});

describe('Acceso con Token', function () {
    
    test('acceso permitido con token válido', function () {
        $role = Role::where('name', 'admin')->first();
        
        $user = User::factory()->create([
            'role_id' => $role->id,
            'activo' => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                    'role_id',
                    'role' => [
                        'id',
                        'name',
                    ],
                    'activo',
                ],
            ]);
    });

    test('acceso denegado sin token', function () {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    });

    test('acceso denegado con token inválido', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_token',
        ])->getJson('/api/me');

        $response->assertStatus(401);
    });
});

describe('Endpoint /me', function () {
    
    test('devuelve usuario autenticado con rol cargado', function () {
        $roleAdmin = Role::where('name', 'admin')->first();
        
        $user = User::factory()->create([
            'role_id' => $roleAdmin->id,
            'activo' => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role.name', 'admin')
            ->assertJsonPath('data.role.id', $roleAdmin->id);
    });

    test('el campo role no es null', function () {
        $role = Role::where('name', 'estudiante')->first();
        
        $user = User::factory()->create([
            'role_id' => $role->id,
            'activo' => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/me');

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.role'));
    });
});
