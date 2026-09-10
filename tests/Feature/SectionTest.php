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

describe('CRUD Sections - Admin', function () {
    test('admin puede listar secciones', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        Section::factory()->count(3)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/sections');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'grade_id', 'name']
                ],
                'meta' => ['current_page', 'last_page', 'total']
            ]);
    });

    test('admin puede crear sección', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create();

        Sanctum::actingAs($admin);

        $sectionData = [
            'grade_id' => $grade->id,
            'school_year_id' => $schoolYear->id,
            'name' => 'A',
        ];

        $response = $this->postJson('/api/sections', $sectionData);

        $response->assertStatus(201);
    });

    test('admin puede ver sección específica', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $section = Section::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/sections/{$section->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $section->id,
                    'name' => $section->name
                ]
            ]);
    });

    test('admin puede actualizar sección', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $section = Section::factory()->create(['name' => 'A']);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/sections/{$section->id}", [
            'name' => 'B'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('sections', ['id' => $section->id, 'name' => 'B']);
    });

    test('admin puede eliminar sección (soft delete)', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $section = Section::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/sections/{$section->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('sections', ['id' => $section->id]);
    });
});

describe('Validaciones Section', function () {
    test('crear sección sin grade_id falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $schoolYear = SchoolYear::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/sections', [
            'school_year_id' => $schoolYear->id,
            'name' => 'A',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['grade_id']);
    });

    test('crear sección sin nombre falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/sections', [
            'grade_id' => $grade->id,
            'school_year_id' => $schoolYear->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    test('profesor no puede crear sección', function () {
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create();

        Sanctum::actingAs($profesor);

        $response = $this->postJson('/api/sections', [
            'grade_id' => $grade->id,
            'school_year_id' => $schoolYear->id,
            'name' => 'A',
        ]);

        $response->assertStatus(403);
    });

    test('estudiante no puede crear sección', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true]);
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create();

        Sanctum::actingAs($estudiante);

        $response = $this->postJson('/api/sections', [
            'grade_id' => $grade->id,
            'school_year_id' => $schoolYear->id,
            'name' => 'A',
        ]);

        $response->assertStatus(403);
    });
});

describe('Section - Relaciones', function () {
    test('sección pertenece a grado', function () {
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);

        $this->assertEquals($grade->id, $section->grade->id);
    });

    test('sección pertenece a año lectivo', function () {
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);

        $this->assertEquals($schoolYear->id, $section->schoolYear->id);
    });

    test('sección tiene estudiantes', function () {
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $student = User::factory()->create([
            'role_id' => $estudianteRole->id,
            'section_id' => $section->id,
            'activo' => true
        ]);

        $this->assertTrue($section->students->contains($student));
    });

    test('sección tiene método getFullNameAttribute', function () {
        $schoolYear = SchoolYear::factory()->create(['name' => '2025']);
        $grade = Grade::factory()->create(['name' => '1er Grado']);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id, 'name' => 'A']);

        // El full_name ahora incluye el año lectivo
        $this->assertEquals('1er Grado - A (2025)', $section->full_name);
    });
});

describe('Section - Filtros', function () {
    test('puede filtrar secciones por grade_id', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        $schoolYear = SchoolYear::factory()->create();
        $grade1 = Grade::factory()->create();
        $grade2 = Grade::factory()->create();

        // Crear secciones con nombres únicos para evitar unique constraint violation
        Section::factory()->create([
            'grade_id' => $grade1->id, 
            'school_year_id' => $schoolYear->id,
            'name' => 'A-' . uniqid()
        ]);
        Section::factory()->create([
            'grade_id' => $grade1->id, 
            'school_year_id' => $schoolYear->id,
            'name' => 'B-' . uniqid()
        ]);
        Section::factory()->create([
            'grade_id' => $grade2->id, 
            'school_year_id' => $schoolYear->id,
            'name' => 'C-' . uniqid()
        ]);
        Section::factory()->create([
            'grade_id' => $grade2->id, 
            'school_year_id' => $schoolYear->id,
            'name' => 'D-' . uniqid()
        ]);
        Section::factory()->create([
            'grade_id' => $grade2->id, 
            'school_year_id' => $schoolYear->id,
            'name' => 'E-' . uniqid()
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/sections?grade_id={$grade1->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    });

    test('puede filtrar secciones por school_year_id', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        $year1 = SchoolYear::factory()->create();
        $year2 = SchoolYear::factory()->create();

        $grade = Grade::factory()->create();

        // Crear secciones con nombres únicos
        Section::factory()->create([
            'grade_id' => $grade->id, 
            'school_year_id' => $year1->id,
            'name' => 'F-' . uniqid()
        ]);
        Section::factory()->create([
            'grade_id' => $grade->id, 
            'school_year_id' => $year1->id,
            'name' => 'G-' . uniqid()
        ]);
        Section::factory()->create([
            'grade_id' => $grade->id, 
            'school_year_id' => $year2->id,
            'name' => 'H-' . uniqid()
        ]);
        Section::factory()->create([
            'grade_id' => $grade->id, 
            'school_year_id' => $year2->id,
            'name' => 'I-' . uniqid()
        ]);
        Section::factory()->create([
            'grade_id' => $grade->id, 
            'school_year_id' => $year2->id,
            'name' => 'J-' . uniqid()
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/sections?school_year_id={$year1->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    });
});