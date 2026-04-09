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
                    '*' => ['id', 'grade_id', 'school_year_id', 'name']
                ],
                'current_page',
                'last_page',
                'total'
            ]);
    });

    test('admin puede crear sección', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);

        Sanctum::actingAs($admin);

        $sectionData = [
            'grade_id' => $grade->id,
            'school_year_id' => $schoolYear->id,
            'name' => 'A',
        ];

        $response = $this->postJson('/api/sections', $sectionData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('sections', [
            'grade_id' => $grade->id,
            'school_year_id' => $schoolYear->id,
            'name' => 'A'
        ]);
    });

    test('admin puede ver sección específica', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $section = Section::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/sections/{$section->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $section->id, 'name' => $section->name]);
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

    test('crear sección sin school_year_id falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $grade = Grade::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/sections', [
            'grade_id' => $grade->id,
            'name' => 'A',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['school_year_id']);
    });

    test('crear sección sin nombre falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/sections', [
            'grade_id' => $grade->id,
            'school_year_id' => $schoolYear->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    test('crear sección con nombre duplicado en el mismo grado y año falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $existingSection = Section::factory()->create([
            'grade_id' => $grade->id,
            'school_year_id' => $schoolYear->id,
            'name' => 'A'
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/sections', [
            'grade_id' => $grade->id,
            'school_year_id' => $schoolYear->id,
            'name' => 'A',
        ]);

        $response->assertStatus(422);
    });

    test('profesor no puede crear sección', function () {
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);

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
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);

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
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);

        $this->assertEquals($grade->id, $section->grade->id);
    });

    test('sección pertenece a año lectivo', function () {
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);

        $this->assertEquals($schoolYear->id, $section->schoolYear->id);
    });

    test('sección tiene estudiantes', function () {
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
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
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id, 'name' => '1er Grado']);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id, 'name' => 'A']);

        $this->assertEquals('1er Grado - A', $section->full_name);
    });
});

describe('Section - Filtros', function () {
    test('puede filtrar secciones por grade_id', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        $schoolYear = SchoolYear::factory()->create();
        $grade1 = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $grade2 = Grade::factory()->create(['school_year_id' => $schoolYear->id]);

        Section::factory()->count(2)->create(['grade_id' => $grade1->id, 'school_year_id' => $schoolYear->id]);
        Section::factory()->count(3)->create(['grade_id' => $grade2->id, 'school_year_id' => $schoolYear->id]);

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

        $grade1 = Grade::factory()->create(['school_year_id' => $year1->id]);
        $grade2 = Grade::factory()->create(['school_year_id' => $year2->id]);

        Section::factory()->count(2)->create(['grade_id' => $grade1->id, 'school_year_id' => $year1->id]);
        Section::factory()->count(3)->create(['grade_id' => $grade2->id, 'school_year_id' => $year2->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/sections?school_year_id={$year1->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    });
});
