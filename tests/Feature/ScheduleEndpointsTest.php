<?php

use App\Models\ClassSchedule;
use App\Models\Grade;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'estudiante']);
    Role::firstOrCreate(['name' => 'profesor']);
});

describe('Endpoint /api/my-schedule', function () {
    test('profesor obtiene su horario como profesor', function () {
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        
        $schoolYear = SchoolYear::factory()->create(['active' => true]);
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        $subject = Subject::factory()->create();

        ClassSchedule::factory()->create([
            'teacher_id' => $profesor->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        Sanctum::actingAs($profesor);

        $response = $this->getJson('/api/my-schedule');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['role', 'school_year_id']
            ]);
        $response->assertJsonPath('meta.role', 'teacher');
    });

    test('estudiante obtiene el horario de su sección', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        $schoolYear = SchoolYear::factory()->create(['active' => true]);
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        
        $estudiante = User::factory()->create([
            'role_id' => $estudianteRole->id,
            'section_id' => $section->id,
            'activo' => true
        ]);

        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        $subject = Subject::factory()->create();

        ClassSchedule::factory()->create([
            'teacher_id' => $profesor->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        Sanctum::actingAs($estudiante);

        $response = $this->getJson('/api/my-schedule');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['role', 'section_id', 'school_year_id']
            ]);
        $response->assertJsonPath('meta.role', 'student');
        $response->assertJsonPath('meta.section_id', $section->id);
    });

    test('estudiante sin sección asignada retorna 404', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        $estudiante = User::factory()->create([
            'role_id' => $estudianteRole->id,
            'section_id' => null,
            'activo' => true
        ]);

        Sanctum::actingAs($estudiante);

        $response = $this->getJson('/api/my-schedule');

        $response->assertStatus(404);
    });


    test('profesor puede filtrar por school_year_id', function () {
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        
        $schoolYear1 = SchoolYear::factory()->create(['active' => true]);
        $schoolYear2 = SchoolYear::factory()->create();
        
        $grade1 = Grade::factory()->create(['school_year_id' => $schoolYear1->id]);
        $grade2 = Grade::factory()->create(['school_year_id' => $schoolYear2->id]);
        
        $section1 = Section::factory()->create(['grade_id' => $grade1->id, 'school_year_id' => $schoolYear1->id]);
        $section2 = Section::factory()->create(['grade_id' => $grade2->id, 'school_year_id' => $schoolYear2->id]);
        
        $subject = Subject::factory()->create();

        // Horario en año 1
        ClassSchedule::factory()->create([
            'teacher_id' => $profesor->id,
            'subject_id' => $subject->id,
            'section_id' => $section1->id,
            'school_year_id' => $schoolYear1->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        // Horario en año 2
        ClassSchedule::factory()->create([
            'teacher_id' => $profesor->id,
            'subject_id' => $subject->id,
            'section_id' => $section2->id,
            'school_year_id' => $schoolYear2->id,
            'day' => 'Martes',
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        Sanctum::actingAs($profesor);

        $response = $this->getJson("/api/my-schedule?school_year_id={$schoolYear1->id}");

        $response->assertStatus(200);
        // Solo debe tener 1 horario (del año 1)
        $response->assertJsonCount(1, 'data');
    });
});

describe('Endpoint /api/sections/{id}/schedule', function () {
    test('puede obtener horario de una sección', function () {
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);

        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        $subject = Subject::factory()->create();

        ClassSchedule::factory()->create([
            'teacher_id' => $profesor->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $response = $this->getJson("/api/sections/{$section->id}/schedule");

        $response->assertStatus(200);
        $response->assertJsonStructure(['Lunes']);
    });

    test('puede filtrar horario de sección por school_year_id', function () {
        $schoolYear1 = SchoolYear::factory()->create();
        $schoolYear2 = SchoolYear::factory()->create();
        
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear1->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear1->id]);

        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        $subject = Subject::factory()->create();

        ClassSchedule::factory()->create([
            'teacher_id' => $profesor->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear1->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $response = $this->getJson("/api/sections/{$section->id}/schedule?school_year_id={$schoolYear1->id}");

        $response->assertStatus(200);
    });
});

describe('Endpoint /api/teachers/{id}/schedule', function () {
    test('puede obtener horario de un profesor', function () {
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);

        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        $subject = Subject::factory()->create();

        ClassSchedule::factory()->create([
            'teacher_id' => $profesor->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $response = $this->getJson("/api/teachers/{$profesor->id}/schedule");

        $response->assertStatus(200);
        $response->assertJsonStructure(['*' => ['id', 'day', 'start_time', 'end_time']]);
    });

    test('puede filtrar horario de profesor por school_year_id', function () {
        $schoolYear1 = SchoolYear::factory()->create();
        $schoolYear2 = SchoolYear::factory()->create();
        
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear1->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear1->id]);

        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        $subject = Subject::factory()->create();

        ClassSchedule::factory()->create([
            'teacher_id' => $profesor->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear1->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $response = $this->getJson("/api/teachers/{$profesor->id}/schedule?school_year_id={$schoolYear1->id}");

        $response->assertStatus(200);
    });
});

describe('Endpoint /api/students/{sectionId}/schedule', function () {
    test('puede obtener horario de estudiante por sección', function () {
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);

        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        $subject = Subject::factory()->create();

        ClassSchedule::factory()->create([
            'teacher_id' => $profesor->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $response = $this->getJson("/api/students/{$section->id}/schedule");

        $response->assertStatus(200)
            ->assertJsonStructure(['Lunes']);
    });
});

describe('Asignación de estudiante a sección', function () {
    test('admin puede asignar sección a estudiante', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true, 'section_id' => null]);
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/users/{$estudiante->id}/assign-section", [
            'section_id' => $section->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $estudiante->id,
            'section_id' => $section->id
        ]);
    });

    test('asignar sección a no-estudiante falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/users/{$profesor->id}/assign-section", [
            'section_id' => $section->id,
        ]);

        $response->assertStatus(422);
    });

    test('profesor no puede asignar sección a estudiante', function () {
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true, 'section_id' => null]);
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);

        Sanctum::actingAs($profesor);

        $response = $this->patchJson("/api/users/{$estudiante->id}/assign-section", [
            'section_id' => $section->id,
        ]);

        $response->assertStatus(403);
    });

    test('estudiante no puede asignarse sección a sí mismo', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true, 'section_id' => null]);
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);

        Sanctum::actingAs($estudiante);

        $response = $this->patchJson("/api/users/{$estudiante->id}/assign-section", [
            'section_id' => $section->id,
        ]);

        $response->assertStatus(403);
    });
});
