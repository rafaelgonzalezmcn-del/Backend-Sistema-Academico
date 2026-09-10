<?php

use App\Models\User;
use App\Models\Grade;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\ClassSchedule;
use App\Models\StudentCourse;
use App\Models\Role;
use App\Services\PromotionService;
use App\Services\UserService;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'estudiante']);
    Role::firstOrCreate(['name' => 'profesor']);
});

/**
 * Flujo de Promoción Académica - Test de Integración
 * 
 * Este test verifica el flujo completo:
 * 1. Crear año lectivo activo
 * 2. Crear grados y secciones
 * 3. Matricular estudiantes
 * 4. Asignar notas
 * 5. Ejecutar promoción
 * 6. Verificar resultado
 */
describe('Flujo de Promoción Académica - End-to-End', function () {
    
    test('flujo completo: crear estructura, matricular, calificar y promover estudiante', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $adminRole = Role::where('name', 'admin')->first();
        
        // Step 1: Crear año lectivo activo
        $currentYear = SchoolYear::factory()->create([
            'active' => true,
            'name' => '2025',
            'grade_order' => 1,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]);
        
        $nextYear = SchoolYear::factory()->create([
            'active' => false,
            'name' => '2026',
            'grade_order' => 2,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        
        // Step 2: Crear grados y secciones
        $currentGrade = Grade::factory()->create([
            'name' => '1er Grado',
            'grade_order' => 1,
        ]);
        
        $nextGrade = Grade::factory()->create([
            'name' => '2do Grado',
            'grade_order' => 2,
        ]);
        
        $currentSection = Section::factory()->create([
            'grade_id' => $currentGrade->id,
            'school_year_id' => $currentYear->id,
            'name' => 'A',
            'max_capacity' => 30,
        ]);
        
        $nextSection = Section::factory()->create([
            'grade_id' => $nextGrade->id,
            'school_year_id' => $nextYear->id,
            'name' => 'A',
            'max_capacity' => 30,
        ]);
        
        // Step 3: Crear materias y horarios
        $materia1 = Subject::factory()->create(['name' => 'Matemáticas']);
        $materia2 = Subject::factory()->create(['name' => 'Español']);
        
        ClassSchedule::factory()->create([
            'section_id' => $currentSection->id,
            'subject_id' => $materia1->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '09:00',
        ]);
        
        ClassSchedule::factory()->create([
            'section_id' => $currentSection->id,
            'subject_id' => $materia2->id,
            'day' => 'Lunes',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);
        
        // Step 4: Matricular estudiante
        $userService = new UserService();
        $estudiante = $userService->create([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'juan.promocion@test.com',
            'password' => 'password123',
            'role_id' => $estudianteRole->id,
            'section_id' => $currentSection->id,
            'activo' => true,
        ]);
        
        expect($estudiante->section_id)->toBe($currentSection->id);
        expect($estudiante->role_id)->toBe($estudianteRole->id);
        
        // Step 5: Asignar notas (crear cursos concluidos)
        StudentCourse::factory()->create([
            'student_id' => $estudiante->id,
            'subject_id' => $materia1->id,
            'section_id' => $currentSection->id,
            'school_year_id' => $currentYear->id,
            'status' => 'concluido',
            'final_grade' => 85,
            'closed_at' => now(),
        ]);
        
        StudentCourse::factory()->create([
            'student_id' => $estudiante->id,
            'subject_id' => $materia2->id,
            'section_id' => $currentSection->id,
            'school_year_id' => $currentYear->id,
            'status' => 'concluido',
            'final_grade' => 90,
            'closed_at' => now(),
        ]);
        
        // Step 6: Verificar elegibilidad antes de promoción
        $promotionService = new PromotionService();
        $eligibility = $promotionService->checkEligibility($estudiante->id);
        
        expect($eligibility['eligible'])->toBeTrue();
        expect($eligibility['subjects_approved'])->toBe(2);
        expect($eligibility['subjects_failed'])->toBe(0);
        expect($eligibility['approval_percentage'])->toBe(100.0);
        
        // Step 7: Ejecutar promoción
        $result = $promotionService->promoteStudent($estudiante->id, $nextSection->id, 'promote');
        
        expect($result['success'])->toBeTrue();
        expect($result['move_type'])->toBe('promote');
        
        // Step 8: Verificar que el estudiante fue promovido
        $estudiante->refresh();
        expect($estudiante->section_id)->toBe($nextSection->id);
        
        // Verificar que la sección destino es la correcta
        expect($estudiante->section->grade_id)->toBe($nextGrade->id);
        expect($estudiante->section->school_year_id)->toBe($nextYear->id);
    });
    
    test('flujo de promoción falla cuando hay materias reprobadas', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        // Setup: Año lectivo activo
        $currentYear = SchoolYear::factory()->create([
            'active' => true,
            'name' => '2025',
            'grade_order' => 1,
        ]);
        
        $nextYear = SchoolYear::factory()->create([
            'active' => false,
            'name' => '2026',
            'grade_order' => 2,
        ]);
        
        $currentGrade = Grade::factory()->create(['grade_order' => 1]);
        $currentSection = Section::factory()->create([
            'grade_id' => $currentGrade->id,
            'school_year_id' => $currentYear->id,
        ]);
        
        $nextGrade = Grade::factory()->create(['grade_order' => 2]);
        $nextSection = Section::factory()->create([
            'grade_id' => $nextGrade->id,
            'school_year_id' => $nextYear->id,
        ]);
        
        $materia = Subject::factory()->create();
        ClassSchedule::factory()->create([
            'section_id' => $currentSection->id,
            'subject_id' => $materia->id,
        ]);
        
        // Matricular estudiante
        $userService = new UserService();
        $estudiante = $userService->create([
            'first_name' => 'María',
            'last_name' => 'García',
            'email' => 'maria.reprobada@test.com',
            'password' => 'password123',
            'role_id' => $estudianteRole->id,
            'section_id' => $currentSection->id,
            'activo' => true,
        ]);
        
        // Crear curso reprobado
        StudentCourse::factory()->create([
            'student_id' => $estudiante->id,
            'subject_id' => $materia->id,
            'section_id' => $currentSection->id,
            'school_year_id' => $currentYear->id,
            'status' => 'reprobado',
            'final_grade' => 45,
            'closed_at' => now(),
        ]);
        
        // Verificar que NO es elegible
        $promotionService = new PromotionService();
        $eligibility = $promotionService->checkEligibility($estudiante->id);
        
        expect($eligibility['eligible'])->toBeFalse();
        expect($eligibility['subjects_failed'])->toBe(1);
        
        // Intentar promoción debe fallar
        expect(function () use ($promotionService, $estudiante, $nextSection) {
            $promotionService->promoteStudent($estudiante->id, $nextSection->id, 'promote');
        })->toThrow(\Illuminate\Validation\ValidationException::class);
    });
    
    test('flujo de promoción permite repetir grado aunque no sea elegible', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        // Setup
        $currentYear = SchoolYear::factory()->create([
            'active' => true,
            'grade_order' => 1,
        ]);
        
        $nextYear = SchoolYear::factory()->create([
            'active' => false,
            'grade_order' => 2,
        ]);
        
        $currentGrade = Grade::factory()->create();
        $currentSection = Section::factory()->create([
            'grade_id' => $currentGrade->id,
            'school_year_id' => $currentYear->id,
        ]);
        
        // Sección para repetir en el siguiente año lectivo
        $repeatSection = Section::factory()->create([
            'grade_id' => $currentGrade->id,
            'school_year_id' => $nextYear->id,
            'name' => 'B',
        ]);
        
        $materia = Subject::factory()->create();
        ClassSchedule::factory()->create([
            'section_id' => $currentSection->id,
            'subject_id' => $materia->id,
        ]);
        
        // Matricular estudiante
        $userService = new UserService();
        $estudiante = $userService->create([
            'first_name' => 'Pedro',
            'last_name' => 'Rodríguez',
            'email' => 'pedro.repetir@test.com',
            'password' => 'password123',
            'role_id' => $estudianteRole->id,
            'section_id' => $currentSection->id,
            'activo' => true,
        ]);
        
        // Curso reprobado
        StudentCourse::factory()->create([
            'student_id' => $estudiante->id,
            'subject_id' => $materia->id,
            'section_id' => $currentSection->id,
            'school_year_id' => $currentYear->id,
            'status' => 'reprobado',
            'final_grade' => 40,
            'closed_at' => now(),
        ]);
        
        // Repetir grado con move_type=repeat
        $promotionService = new PromotionService();
        $result = $promotionService->promoteStudent($estudiante->id, $repeatSection->id, 'repeat');
        
        expect($result['success'])->toBeTrue();
        expect($result['move_type'])->toBe('repeat');
        
        $estudiante->refresh();
        expect($estudiante->section_id)->toBe($repeatSection->id);
    });
    
    test('flujo de sección completa: promueve todos los estudiantes elegibles', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        // Setup
        $currentYear = SchoolYear::factory()->create([
            'active' => true,
            'grade_order' => 1,
        ]);
        
        $nextYear = SchoolYear::factory()->create([
            'active' => false,
            'grade_order' => 2,
        ]);
        
        $currentGrade = Grade::factory()->create(['grade_order' => 1]);
        $currentSection = Section::factory()->create([
            'grade_id' => $currentGrade->id,
            'school_year_id' => $currentYear->id,
        ]);
        
        $nextGrade = Grade::factory()->create(['grade_order' => 2]);
        $nextSection = Section::factory()->create([
            'grade_id' => $nextGrade->id,
            'school_year_id' => $nextYear->id,
        ]);
        
        $materia = Subject::factory()->create();
        ClassSchedule::factory()->create([
            'section_id' => $currentSection->id,
            'subject_id' => $materia->id,
        ]);
        
        // Crear 3 estudiantes: 2 elegibles, 1 no elegible
        $estudiantesElegibles = [];
        
        for ($i = 1; $i <= 2; $i++) {
            $userService = new UserService();
            $estudiante = $userService->create([
                'first_name' => "Estudiante {$i}",
                'last_name' => 'Elegible',
                'email' => "elegible{$i}@test.com",
                'password' => 'password123',
                'role_id' => $estudianteRole->id,
                'section_id' => $currentSection->id,
                'activo' => true,
            ]);
            
            StudentCourse::factory()->create([
                'student_id' => $estudiante->id,
                'subject_id' => $materia->id,
                'section_id' => $currentSection->id,
                'school_year_id' => $currentYear->id,
                'status' => 'concluido',
                'final_grade' => 80 + $i,
                'closed_at' => now(),
            ]);
            
            $estudiantesElegibles[] = $estudiante;
        }
        
        // Estudiante no elegible (reprobado)
        $userService = new UserService();
        $estudianteNoElegible = $userService->create([
            'first_name' => 'Estudiante No Elegible',
            'last_name' => 'Reprobado',
            'email' => 'no-elegible@test.com',
            'password' => 'password123',
            'role_id' => $estudianteRole->id,
            'section_id' => $currentSection->id,
            'activo' => true,
        ]);
        
        StudentCourse::factory()->create([
            'student_id' => $estudianteNoElegible->id,
            'subject_id' => $materia->id,
            'section_id' => $currentSection->id,
            'school_year_id' => $currentYear->id,
            'status' => 'reprobado',
            'final_grade' => 45,
            'closed_at' => now(),
        ]);
        
        // Ejecutar promoción de sección
        $promotionService = new PromotionService();
        $result = $promotionService->promoteSection($currentSection->id, $nextGrade->id, 'promote');
        
        expect($result['total'])->toBe(3);
        expect($result['promoted'] + $result['skipped'])->toBe(3);
        
        // Verificar que los elegibles fueron promovidos
        foreach ($estudiantesElegibles as $estudiante) {
            $estudiante->refresh();
            expect($estudiante->section_id)->toBe($nextSection->id);
        }
        
        // Verificar que el no elegible NO fue promovido (skipped)
        $estudianteNoElegible->refresh();
        expect($estudianteNoElegible->section_id)->toBe($currentSection->id);
    });
});