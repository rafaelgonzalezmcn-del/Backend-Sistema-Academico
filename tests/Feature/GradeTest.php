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
                    '*' => ['id', 'name', 'school_year_id']
                ],
                'current_page',
                'last_page',
                'total'
            ]);
    });

    test('admin puede crear grado', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $schoolYear = SchoolYear::factory()->create();

        Sanctum::actingAs($admin);

        $gradeData = [
            'name' => '1er Grado',
            'school_year_id' => $schoolYear->id,
        ];

        $response = $this->postJson('/api/grades', $gradeData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('grades', ['name' => '1er Grado', 'school_year_id' => $schoolYear->id]);
    });

    test('admin puede ver grado específico', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $grade = Grade::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/grades/{$grade->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $grade->id, 'name' => $grade->name]);
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
        $this->assertDatabaseHas('grades', ['id' => $grade->id, 'name' => 'Grado Actualizado']);
    });

    test('admin puede eliminar grado (soft delete)', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $grade = Grade::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/grades/{$grade->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('grades', ['id' => $grade->id]);
    });
});

describe('Validaciones Grade', function () {
    test('crear grado sin nombre falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $schoolYear = SchoolYear::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/grades', [
            'school_year_id' => $schoolYear->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    test('crear grado sin school_year_id falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/grades', [
            'name' => 'Nuevo Grado',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['school_year_id']);
    });

    test('crear grado con school_year_id inexistente falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/grades', [
            'name' => 'Nuevo Grado',
            'school_year_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['school_year_id']);
    });

    test('profesor no puede crear grado', function () {
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        $schoolYear = SchoolYear::factory()->create();

        Sanctum::actingAs($profesor);

        $response = $this->postJson('/api/grades', [
            'name' => 'Nuevo Grado',
            'school_year_id' => $schoolYear->id,
        ]);

        $response->assertStatus(403);
    });

    test('estudiante no puede crear grado', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true]);
        $schoolYear = SchoolYear::factory()->create();

        Sanctum::actingAs($estudiante);

        $response = $this->postJson('/api/grades', [
            'name' => 'Nuevo Grado',
            'school_year_id' => $schoolYear->id,
        ]);

        $response->assertStatus(403);
    });
});

describe('Grade - Relaciones', function () {
    test('grado pertenece a año lectivo', function () {
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);

        $this->assertEquals($schoolYear->id, $grade->schoolYear->id);
    });

    test('grado tiene secciones', function () {
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);

        $this->assertTrue($grade->sections->contains($section));
    });

    test('grado se carga con año lectivo en respuesta', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $grade = Grade::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/grades/{$grade->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'name', 'school_year_id', 'schoolYear' => ['id', 'name']]);
    });
});

describe('Grade - Filtros', function () {
    test('puede filtrar grados por school_year_id', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        $year1 = SchoolYear::factory()->create();
        $year2 = SchoolYear::factory()->create();

        Grade::factory()->count(2)->create(['school_year_id' => $year1->id]);
        Grade::factory()->count(3)->create(['school_year_id' => $year2->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/grades?school_year_id={$year1->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    });
});
