<?php

use App\Models\Grade;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'estudiante']);
    Role::firstOrCreate(['name' => 'profesor']);
});

describe('CRUD Grades - Admin', function () {
    test('admin puede listar grados', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        Grade::factory()->count(3)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/grades');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name']
                ],
                'meta' => ['current_page', 'last_page', 'total']
            ]);
    });

    test('admin puede crear grado', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        Sanctum::actingAs($admin);

        $gradeData = [
            'name' => '1er Grado',
            'grade_order' => 1,
        ];

        $response = $this->postJson('/api/grades', $gradeData);

        $response->assertStatus(201);
    });

    test('admin puede ver grado específico', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $grade = Grade::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/grades/{$grade->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $grade->id);
    });

    test('admin puede actualizar grado', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $grade = Grade::factory()->create(['name' => 'Viejo']);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/grades/{$grade->id}", [
            'name' => 'Grado Actualizado'
        ]);

        $response->assertStatus(200);
    });

    test('admin puede eliminar grado (soft delete)', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $grade = Grade::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/grades/{$grade->id}");

        $response->assertStatus(200);
    });
});

describe('Validaciones Grade', function () {
    test('crear grado sin nombre falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/grades', [
            'grade_order' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    test('profesor no puede crear grado', function () {
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);

        Sanctum::actingAs($profesor);

        $response = $this->postJson('/api/grades', [
            'name' => 'Nuevo Grado',
            'grade_order' => 1,
        ]);

        $response->assertStatus(403);
    });

    test('estudiante no puede crear grado', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true]);

        Sanctum::actingAs($estudiante);

        $response = $this->postJson('/api/grades', [
            'name' => 'Nuevo Grado',
            'grade_order' => 1,
        ]);

        $response->assertStatus(403);
    });
});

describe('Grade - Relaciones', function () {
    test('grado tiene secciones', function () {
        $grade = Grade::factory()->create();
        $schoolYear = SchoolYear::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);

        $this->assertTrue($grade->sections->contains($section));
    });

    test('grado tiene método grade_order', function () {
        $grade = Grade::factory()->create(['grade_order' => 5]);

        $this->assertEquals(5, $grade->grade_order);
    });
});

describe('Grade - Filtros', function () {
    test('puede listar todos los grados', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        Grade::factory()->count(3)->create();
        Grade::factory()->count(2)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/grades');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
    });
});