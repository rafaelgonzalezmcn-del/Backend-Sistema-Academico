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
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        $subject = Subject::factory()->create();

        ClassSchedule::factory()->create([
            'teacher_id' => $profesor->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        Sanctum::actingAs($profesor);

        $response = $this->getJson('/api/my-schedule');

        $response->assertStatus(200);
    });

    test('estudiante obtiene el horario de su sección', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true]);
        
        $schoolYear = SchoolYear::factory()->create(['active' => true]);
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        $estudiante->update(['section_id' => $section->id]);

        $subject = Subject::factory()->create();
        ClassSchedule::factory()->create([
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        Sanctum::actingAs($estudiante);

        $response = $this->getJson('/api/my-schedule');

        $response->assertStatus(200);
    });

    test('estudiante sin sección asignada retorna error', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true, 'section_id' => null]);

        Sanctum::actingAs($estudiante);

        $response = $this->getJson('/api/my-schedule');

        $response->assertStatus(400);
    });
});

describe('Endpoint /api/sections/{id}/schedule', function () {
    test('puede obtener horario de una sección', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        
        $subject = Subject::factory()->create();
        ClassSchedule::factory()->create([
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'day' => 'Lunes',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/sections/{$section->id}/schedule");

        $response->assertStatus(200);
    });
});

describe('Endpoint /api/teachers/{id}/schedule', function () {
    test('puede obtener horario de un profesor', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);

        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        $subject = Subject::factory()->create();

        ClassSchedule::factory()->create([
            'teacher_id' => $profesor->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'day' => 'Lunes',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/teachers/{$profesor->id}/schedule");

        $response->assertStatus(200);
    });
});

describe('Asignación de estudiante a sección', function () {
    test('admin puede asignar sección a estudiante', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true]);

        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/users/{$estudiante->id}", [
            'section_id' => $section->id
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $estudiante->id,
            'section_id' => $section->id
        ]);
    });
});