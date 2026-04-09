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

describe('CRUD ClassSchedules - Admin', function () {
    test('admin puede listar horarios', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        ClassSchedule::factory()->count(3)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/class-schedules');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'last_page',
                'total'
            ]);
    });

    test('admin puede crear horario', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        $subject = Subject::factory()->create();

        Sanctum::actingAs($admin);

        $scheduleData = [
            'teacher_id' => $profesor->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ];

        $response = $this->postJson('/api/class-schedules', $scheduleData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('class_schedules', [
            'teacher_id' => $profesor->id,
            'section_id' => $section->id,
            'day' => 'Lunes',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
        ]);
    });

    test('admin puede ver horario específico', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $schedule = ClassSchedule::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/class-schedules/{$schedule->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $schedule->id]);
    });

    test('admin puede actualizar horario', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $schedule = ClassSchedule::factory()->create(['day' => 'Lunes', 'start_time' => '08:00', 'end_time' => '10:00']);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/class-schedules/{$schedule->id}", [
            'day' => 'Martes',
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('class_schedules', ['id' => $schedule->id, 'day' => 'Martes']);
    });

    test('admin puede eliminar horario (soft delete)', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $schedule = ClassSchedule::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/class-schedules/{$schedule->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('class_schedules', ['id' => $schedule->id]);
    });
});

describe('Validaciones ClassSchedule', function () {
    test('crear horario sin teacher_id falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        $subject = Subject::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/class-schedules', [
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['teacher_id']);
    });

    test('crear horario con teacher_id que no es profesor falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true]);
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        $subject = Subject::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/class-schedules', [
            'teacher_id' => $estudiante->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $response->assertStatus(422);
    });

    test('crear horario con día inválido falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        $subject = Subject::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/class-schedules', [
            'teacher_id' => $profesor->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'day' => 'DíaInválido',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['day']);
    });

    test('crear horario sin school_year_id falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id]);
        $subject = Subject::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/class-schedules', [
            'teacher_id' => $profesor->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['school_year_id']);
    });

    test('crear horario con start_time >= end_time falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        $subject = Subject::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/class-schedules', [
            'teacher_id' => $profesor->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'day' => 'Lunes',
            'start_time' => '12:00',
            'end_time' => '10:00',
        ]);

        $response->assertStatus(422);
    });
});

describe('ClassSchedule - Conflictos de Horarios', function () {
    test('no puede crear horario que se solape con otro del mismo profesor', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section1 = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        $section2 = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        $subject = Subject::factory()->create();

        // Horario existente: 08:00 - 10:00
        ClassSchedule::factory()->create([
            'teacher_id' => $profesor->id,
            'section_id' => $section1->id,
            'school_year_id' => $schoolYear->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        Sanctum::actingAs($admin);

        // Nuevo horario que se solapa: 09:00 - 11:00
        $response = $this->postJson('/api/class-schedules', [
            'teacher_id' => $profesor->id,
            'subject_id' => $subject->id,
            'section_id' => $section2->id,
            'school_year_id' => $schoolYear->id,
            'day' => 'Lunes',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $response->assertStatus(422);
    });

    test('no puede crear horario que se solape con otra clase de la misma sección', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor1 = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        $profesor2 = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        $subject1 = Subject::factory()->create();
        $subject2 = Subject::factory()->create();

        // Horario existente: 08:00 - 10:00
        ClassSchedule::factory()->create([
            'teacher_id' => $profesor1->id,
            'subject_id' => $subject1->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        Sanctum::actingAs($admin);

        // Nuevo horario que se solapa: 09:00 - 11:00
        $response = $this->postJson('/api/class-schedules', [
            'teacher_id' => $profesor2->id,
            'subject_id' => $subject2->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'day' => 'Lunes',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $response->assertStatus(422);
    });

    test('puede crear horario que no se solape con otro del mismo profesor', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        $subject1 = Subject::factory()->create();
        $subject2 = Subject::factory()->create();

        // Horario existente: 08:00 - 10:00
        ClassSchedule::factory()->create([
            'teacher_id' => $profesor->id,
            'subject_id' => $subject1->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        Sanctum::actingAs($admin);

        // Nuevo horario que NO se solapa: 10:00 - 12:00
        $response = $this->postJson('/api/class-schedules', [
            'teacher_id' => $profesor->id,
            'subject_id' => $subject2->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'day' => 'Lunes',
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $response->assertStatus(201);
    });

    test('no puede crear horario que se solape en diferente día', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        $subject1 = Subject::factory()->create();
        $subject2 = Subject::factory()->create();

        // Horario existente: Lunes 08:00 - 10:00
        ClassSchedule::factory()->create([
            'teacher_id' => $profesor->id,
            'subject_id' => $subject1->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        Sanctum::actingAs($admin);

        // Nuevo horario: Martes 09:00 - 11:00 (mismo horario pero diferente día)
        $response = $this->postJson('/api/class-schedules', [
            'teacher_id' => $profesor->id,
            'subject_id' => $subject2->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'day' => 'Martes',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $response->assertStatus(201);
    });
});

describe('ClassSchedule - Relaciones', function () {
    test('horario pertenece a profesor', function () {
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        
        $schedule = ClassSchedule::factory()->create(['teacher_id' => $profesor->id]);

        $this->assertEquals($profesor->id, $schedule->teacher->id);
    });

    test('horario pertenece a materia', function () {
        $subject = Subject::factory()->create();
        $schedule = ClassSchedule::factory()->create(['subject_id' => $subject->id]);

        $this->assertEquals($subject->id, $schedule->subject->id);
    });

    test('horario pertenece a sección', function () {
        $section = Section::factory()->create();
        $schedule = ClassSchedule::factory()->create(['section_id' => $section->id]);

        $this->assertEquals($section->id, $schedule->section->id);
    });

    test('horario pertenece a año lectivo', function () {
        $schoolYear = SchoolYear::factory()->create();
        $schedule = ClassSchedule::factory()->create(['school_year_id' => $schoolYear->id]);

        $this->assertEquals($schoolYear->id, $schedule->schoolYear->id);
    });

    test('horario tiene método overlapsWith', function () {
        $schedule1 = ClassSchedule::factory()->create([
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $schedule2 = ClassSchedule::factory()->create([
            'day' => 'Lunes',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $schedule3 = ClassSchedule::factory()->create([
            'day' => 'Martes',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        // Mismo día y se solapan
        $this->assertTrue($schedule1->overlapsWith($schedule2));
        
        // Diferente día
        $this->assertFalse($schedule1->overlapsWith($schedule3));
    });

    test('horario tiene método getDurationInMinutesAttribute', function () {
        $schedule = ClassSchedule::factory()->create([
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $this->assertEquals(120, $schedule->duration_in_minutes);
    });
});

describe('ClassSchedule - school_year_id debe coincidir con sección', function () {
    test('crear horario con school_year_id diferente al de la sección falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);
        
        $schoolYear1 = SchoolYear::factory()->create();
        $schoolYear2 = SchoolYear::factory()->create();
        
        $grade = Grade::factory()->create(['school_year_id' => $schoolYear1->id]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear1->id]);
        
        $subject = Subject::factory()->create();

        Sanctum::actingAs($admin);

        // Usar school_year_id diferente al de la sección
        $response = $this->postJson('/api/class-schedules', [
            'teacher_id' => $profesor->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear2->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $response->assertStatus(422);
    });
});
