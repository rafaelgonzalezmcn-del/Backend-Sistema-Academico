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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'estudiante']);
    Role::firstOrCreate(['name' => 'profesor']);
});

describe('PromotionService - checkEligibility', function () {
    
    test('retorna error cuando estudiante no existe', function () {
        $service = new PromotionService();
        $result = $service->checkEligibility(99999);
        
        expect($result['eligible'])->toBeFalse();
        expect($result['message'])->toContain('no existe');
    });
    
    test('retorna error cuando usuario no es estudiante', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        
        $service = new PromotionService();
        $result = $service->checkEligibility($admin->id);
        
        expect($result['eligible'])->toBeFalse();
        expect($result['message'])->toContain('no es un estudiante');
    });
    
    test('retorna error cuando estudiante no tiene sección', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create([
            'role_id' => $estudianteRole->id,
            'section_id' => null,
            'activo' => true
        ]);
        
        $service = new PromotionService();
        $result = $service->checkEligibility($estudiante->id);
        
        expect($result['eligible'])->toBeFalse();
        expect($result['message'])->toContain('incompletos');
    });
    
    test('retorna error cuando no hay año lectivo activo', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        $schoolYear = SchoolYear::factory()->create(['active' => false]);
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        
        $estudiante = User::factory()->create([
            'role_id' => $estudianteRole->id,
            'section_id' => $section->id,
            'activo' => true
        ]);
        
        $service = new PromotionService();
        $result = $service->checkEligibility($estudiante->id);
        
        expect($result['eligible'])->toBeFalse();
        expect($result['message'])->toContain('año lectivo activo');
    });
    
    test('estudiante elegible cuando tiene todas las materias aprobadas', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        // Crear año lectivo activo
        $schoolYear = SchoolYear::factory()->create(['active' => true]);
        $grade = Grade::factory()->create(['name' => '1er Grado', 'grade_order' => 1]);
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id, 'name' => 'A']);
        
        // Crear materia y horario
        $subject = Subject::factory()->create();
        ClassSchedule::factory()->create([
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'day' => 'Lunes',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);
        
        $estudiante = User::factory()->create([
            'role_id' => $estudianteRole->id,
            'section_id' => $section->id,
            'activo' => true
        ]);
        
        // Crear curso aprobado y cerrado
        StudentCourse::factory()->create([
            'student_id' => $estudiante->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'status' => 'aprobado',
            'final_grade' => 80,
            'closed_at' => now(),
        ]);
        
        $service = new PromotionService();
        $result = $service->checkEligibility($estudiante->id);
        
        expect($result['eligible'])->toBeTrue();
        expect($result['subjects_approved'])->toBe(1);
        expect($result['subjects_failed'])->toBe(0);
        expect($result['subjects_pending'])->toBe(0);
        expect($result['approval_percentage'])->toBe(100.0);
    });
    
    test('estudiante NO elegible cuando tiene materia reprobada', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        $schoolYear = SchoolYear::factory()->create(['active' => true]);
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        
        $subject = Subject::factory()->create();
        ClassSchedule::factory()->create([
            'section_id' => $section->id,
            'subject_id' => $subject->id,
        ]);
        
        $estudiante = User::factory()->create([
            'role_id' => $estudianteRole->id,
            'section_id' => $section->id,
            'activo' => true
        ]);
        
        // Crear curso reprobado
        StudentCourse::factory()->create([
            'student_id' => $estudiante->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'status' => 'reprobado',
            'final_grade' => 50,
            'closed_at' => now(),
        ]);
        
        $service = new PromotionService();
        $result = $service->checkEligibility($estudiante->id);
        
        expect($result['eligible'])->toBeFalse();
        expect($result['subjects_failed'])->toBe(1);
        expect($result['message'])->toContain('reprobada');
    });
    
    test('estudiante NO elegible cuando tiene materia pendiente (sin cerrar)', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        $schoolYear = SchoolYear::factory()->create(['active' => true]);
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        
        $subject = Subject::factory()->create();
        ClassSchedule::factory()->create([
            'section_id' => $section->id,
            'subject_id' => $subject->id,
        ]);
        
        $estudiante = User::factory()->create([
            'role_id' => $estudianteRole->id,
            'section_id' => $section->id,
            'activo' => true
        ]);
        
        // Crear curso cursando (sin cerrar)
        StudentCourse::factory()->create([
            'student_id' => $estudiante->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'status' => 'cursando',
            'final_grade' => null,
            'closed_at' => null,
        ]);
        
        $service = new PromotionService();
        $result = $service->checkEligibility($estudiante->id);
        
        expect($result['eligible'])->toBeFalse();
        expect($result['subjects_pending'])->toBe(1);
        expect($result['message'])->toContain('pendiente');
    });
    
    test('estudiante NO elegible cuando tiene materia sin curso registrado', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        $schoolYear = SchoolYear::factory()->create(['active' => true]);
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        
        // Crear dos materias en el horario
        $subject1 = Subject::factory()->create();
        $subject2 = Subject::factory()->create();
        
        ClassSchedule::factory()->create([
            'section_id' => $section->id,
            'subject_id' => $subject1->id,
        ]);
        ClassSchedule::factory()->create([
            'section_id' => $section->id,
            'subject_id' => $subject2->id,
        ]);
        
        $estudiante = User::factory()->create([
            'role_id' => $estudianteRole->id,
            'section_id' => $section->id,
            'activo' => true
        ]);
        
        // Solo crear curso para una materia (la otra está pendiente/sin registro)
        StudentCourse::factory()->create([
            'student_id' => $estudiante->id,
            'subject_id' => $subject1->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'status' => 'aprobado',
            'final_grade' => 85,
            'closed_at' => now(),
        ]);
        
        $service = new PromotionService();
        $result = $service->checkEligibility($estudiante->id);
        
        expect($result['eligible'])->toBeFalse();
        expect($result['subjects_pending'])->toBe(1);
    });
    
    test('calcula promedio correctamente', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        $schoolYear = SchoolYear::factory()->create(['active' => true]);
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        
        $subject1 = Subject::factory()->create();
        $subject2 = Subject::factory()->create();
        
        ClassSchedule::factory()->create(['section_id' => $section->id, 'subject_id' => $subject1->id]);
        ClassSchedule::factory()->create(['section_id' => $section->id, 'subject_id' => $subject2->id]);
        
        $estudiante = User::factory()->create([
            'role_id' => $estudianteRole->id,
            'section_id' => $section->id,
            'activo' => true
        ]);
        
        StudentCourse::factory()->create([
            'student_id' => $estudiante->id,
            'subject_id' => $subject1->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'status' => 'aprobado',
            'final_grade' => 80,
            'closed_at' => now(),
        ]);
        StudentCourse::factory()->create([
            'student_id' => $estudiante->id,
            'subject_id' => $subject2->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'status' => 'aprobado',
            'final_grade' => 90,
            'closed_at' => now(),
        ]);
        
        $service = new PromotionService();
        $result = $service->checkEligibility($estudiante->id);
        
        expect($result['average_grade'])->toBe(85.0); // (80 + 90) / 2 = 85
    });
    
    test('puede especificar año lectivo específico', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        // Crear dos años lectivos
        $year1 = SchoolYear::factory()->create(['active' => true]);
        $year2 = SchoolYear::factory()->create(['active' => false]);
        
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $year1->id]);
        
        $subject = Subject::factory()->create();
        ClassSchedule::factory()->create([
            'section_id' => $section->id,
            'subject_id' => $subject->id,
        ]);
        
        $estudiante = User::factory()->create([
            'role_id' => $estudianteRole->id,
            'section_id' => $section->id,
            'activo' => true
        ]);
        
        StudentCourse::factory()->create([
            'student_id' => $estudiante->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $year1->id,
            'status' => 'aprobado',
            'final_grade' => 80,
            'closed_at' => now(),
        ]);
        
        $service = new PromotionService();
        $result = $service->checkEligibility($estudiante->id, $year1->id);
        
        expect($result['eligible'])->toBeTrue();
    });
});

describe('PromotionService - evaluateCourses', function () {
    
    test('evalúa colección vacía correctamente', function () {
        $service = new PromotionService();
        $result = $service->evaluateCourses(collect(), collect([1, 2, 3]));
        
        expect($result['approved'])->toBe(0);
        expect($result['failed'])->toBe(0);
        expect($result['pending'])->toBe(3); // Las 3 materias sin curso
    });
    
    test('evalúa cursos mixtos correctamente', function () {
        $service = new PromotionService();
        
        // Crear cursos simulados con closures ejecutables
        $courses = collect([
            new class {
                public int $subject_id = 1;
                public $subject;
                public string $status = 'aprobado';
                public float $final_grade = 85;
                public $schoolYear = null;
                public $section = null;
                public $profesor = null;
                public $closed_at;
                public function __construct() {
                    $this->subject = new class { public string $name = 'Matemáticas'; };
                    $this->closed_at = now();
                }
                public function isClosed(): bool { return true; }
            },
            new class {
                public int $subject_id = 2;
                public $subject;
                public string $status = 'reprobado';
                public float $final_grade = 45;
                public $schoolYear = null;
                public $section = null;
                public $profesor = null;
                public $closed_at;
                public function __construct() {
                    $this->subject = new class { public string $name = 'Español'; };
                    $this->closed_at = now();
                }
                public function isClosed(): bool { return true; }
            },
            new class {
                public int $subject_id = 3;
                public $subject;
                public string $status = 'cursando';
                public ?float $final_grade = null;
                public $schoolYear = null;
                public $section = null;
                public $profesor = null;
                public $closed_at = null;
                public function __construct() {
                    $this->subject = new class { public string $name = 'Ciencias'; };
                }
                public function isClosed(): bool { return false; }
            },
        ]);
        
        $subjectIds = collect([1, 2, 3]);
        
        $result = $service->evaluateCourses($courses, $subjectIds);
        
        expect($result['approved'])->toBe(1);
        expect($result['failed'])->toBe(1);
        expect($result['pending'])->toBe(1);
    });
});

describe('PromotionService - promoteStudent', function () {
    
    test('promueve estudiante elegible correctamente', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        // Año lectivo actual
        $currentYear = SchoolYear::factory()->create(['active' => true, 'name' => '2025', 'grade_order' => 1]);
        $currentGrade = Grade::factory()->create(['name' => '1er Grado', 'grade_order' => 1]);
        $currentSection = Section::factory()->create(['grade_id' => $currentGrade->id, 'school_year_id' => $currentYear->id, 'name' => 'A']);
        
        // Año lectivo siguiente
        $nextYear = SchoolYear::factory()->create(['active' => false, 'name' => '2026', 'grade_order' => 2]);
        $nextGrade = Grade::factory()->create(['name' => '2do Grado', 'grade_order' => 2]);
        $nextSection = Section::factory()->create(['grade_id' => $nextGrade->id, 'school_year_id' => $nextYear->id, 'name' => 'A']);
        
        // Materia y curso aprobado
        $subject = Subject::factory()->create();
        ClassSchedule::factory()->create([
            'section_id' => $currentSection->id,
            'subject_id' => $subject->id,
        ]);
        
        $estudiante = User::factory()->create([
            'role_id' => $estudianteRole->id,
            'section_id' => $currentSection->id,
            'activo' => true
        ]);
        
        StudentCourse::factory()->create([
            'student_id' => $estudiante->id,
            'subject_id' => $subject->id,
            'section_id' => $currentSection->id,
            'school_year_id' => $currentYear->id,
            'status' => 'concluido',
            'final_grade' => 85,
            'closed_at' => now(),
        ]);
        
        $service = new PromotionService();
        $result = $service->promoteStudent($estudiante->id, $nextSection->id, 'promote');
        
        expect($result['success'])->toBeTrue();
        expect($result['move_type'])->toBe('promote');
        expect($result['to_section_id'])->toBe($nextSection->id);
        
        // Verificar que el estudiante cambió de sección
        $estudiante->refresh();
        expect($estudiante->section_id)->toBe($nextSection->id);
    });
    
    test('lanza excepción cuando estudiante no existe', function () {
        $service = new PromotionService();
        
        expect(function () use ($service) {
            $service->promoteStudent(99999, 1, 'promote');
        })->toThrow(\Exception::class, 'no existe');
    });
    
    test('lanza excepción cuando no hay año lectivo siguiente', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        $currentYear = SchoolYear::factory()->create(['active' => true]);
        $currentGrade = Grade::factory()->create();
        $currentSection = Section::factory()->create(['grade_id' => $currentGrade->id, 'school_year_id' => $currentYear->id]);
        
        $estudiante = User::factory()->create([
            'role_id' => $estudianteRole->id,
            'section_id' => $currentSection->id,
            'activo' => true
        ]);
        
        // Sin año siguiente
        $nextGrade = Grade::factory()->create();
        $nextSection = Section::factory()->create(['grade_id' => $nextGrade->id, 'school_year_id' => $currentYear->id]);
        
        $service = new PromotionService();
        
        expect(function () use ($service, $estudiante, $nextSection) {
            $service->promoteStudent($estudiante->id, $nextSection->id, 'promote');
        })->toThrow(\Illuminate\Validation\ValidationException::class);
    });
    
    test('lanza excepción cuando hay materias sin cerrar (move_type=promote)', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        $currentYear = SchoolYear::factory()->create(['active' => true, 'grade_order' => 1]);
        $nextYear = SchoolYear::factory()->create(['active' => false, 'grade_order' => 2]);
        
        $currentGrade = Grade::factory()->create();
        $currentSection = Section::factory()->create(['grade_id' => $currentGrade->id, 'school_year_id' => $currentYear->id]);
        
        $nextGrade = Grade::factory()->create();
        $nextSection = Section::factory()->create(['grade_id' => $nextGrade->id, 'school_year_id' => $nextYear->id]);
        
        $estudiante = User::factory()->create([
            'role_id' => $estudianteRole->id,
            'section_id' => $currentSection->id,
            'activo' => true
        ]);
        
        // Curso sin cerrar
        StudentCourse::factory()->create([
            'student_id' => $estudiante->id,
            'section_id' => $currentSection->id,
            'school_year_id' => $currentYear->id,
            'status' => 'cursando',
            'closed_at' => null,
        ]);
        
        $service = new PromotionService();
        
        expect(function () use ($service, $estudiante, $nextSection) {
            $service->promoteStudent($estudiante->id, $nextSection->id, 'promote');
        })->toThrow(\Illuminate\Validation\ValidationException::class, 'sin cerrar');
    });
    
    test('permite repetir grado aunque no sea elegible (move_type=repeat)', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        $currentYear = SchoolYear::factory()->create(['active' => true, 'grade_order' => 1]);
        $nextYear = SchoolYear::factory()->create(['active' => false, 'grade_order' => 2]);
        
        $currentGrade = Grade::factory()->create();
        $currentSection = Section::factory()->create(['grade_id' => $currentGrade->id, 'school_year_id' => $currentYear->id]);
        
        $repeatSection = Section::factory()->create(['grade_id' => $currentGrade->id, 'school_year_id' => $nextYear->id, 'name' => 'B']);
        
        $estudiante = User::factory()->create([
            'role_id' => $estudianteRole->id,
            'section_id' => $currentSection->id,
            'activo' => true
        ]);
        
        // Cursos concluidos
        StudentCourse::factory()->create([
            'student_id' => $estudiante->id,
            'section_id' => $currentSection->id,
            'school_year_id' => $currentYear->id,
            'status' => 'concluido',
            'closed_at' => now(),
        ]);
        
        $service = new PromotionService();
        $result = $service->promoteStudent($estudiante->id, $repeatSection->id, 'repeat');
        
        expect($result['success'])->toBeTrue();
        expect($result['move_type'])->toBe('repeat');
    });
});

describe('PromotionService - promoteSection', function () {
    
    test('retorna estructura cuando sección no existe', function () {
        $service = new PromotionService();
        $result = $service->promoteSection(99999, 1, 'promote');
        
        expect($result['promoted'])->toBe(0);
        expect($result['total'])->toBe(0);
        expect($result['details'][0]['status'])->toBe('error');
    });
    
    test('promueve sección con estudiantes elegibles', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        $currentYear = SchoolYear::factory()->create(['active' => true, 'grade_order' => 1]);
        $nextYear = SchoolYear::factory()->create(['active' => false, 'grade_order' => 2]);
        
        $currentGrade = Grade::factory()->create(['grade_order' => 1]);
        $currentSection = Section::factory()->create(['grade_id' => $currentGrade->id, 'school_year_id' => $currentYear->id, 'name' => 'A']);
        
        $nextGrade = Grade::factory()->create(['grade_order' => 2]);
        $nextSection = Section::factory()->create(['grade_id' => $nextGrade->id, 'school_year_id' => $nextYear->id, 'name' => 'A']);
        
        $subject = Subject::factory()->create();
        ClassSchedule::factory()->create([
            'section_id' => $currentSection->id,
            'subject_id' => $subject->id,
        ]);
        
        // Estudiante elegible
        $estudiante = User::factory()->create([
            'role_id' => $estudianteRole->id,
            'section_id' => $currentSection->id,
            'activo' => true
        ]);
        
        StudentCourse::factory()->create([
            'student_id' => $estudiante->id,
            'subject_id' => $subject->id,
            'section_id' => $currentSection->id,
            'school_year_id' => $currentYear->id,
            'status' => 'concluido',
            'closed_at' => now(),
        ]);
        
        $service = new PromotionService();
        $result = $service->promoteSection($currentSection->id, $nextGrade->id, 'promote');
        
        expect($result['total'])->toBe(1);
        expect($result['promoted'] + $result['skipped'])->toBe(1);
    });
    
    test('hace soft-fail cuando un estudiante falla', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        $currentYear = SchoolYear::factory()->create(['active' => true, 'grade_order' => 1]);
        $nextYear = SchoolYear::factory()->create(['active' => false, 'grade_order' => 2]);
        
        $currentGrade = Grade::factory()->create(['grade_order' => 1]);
        $currentSection = Section::factory()->create(['grade_id' => $currentGrade->id, 'school_year_id' => $currentYear->id]);
        
        $nextGrade = Grade::factory()->create(['grade_order' => 2]);
        // Sin sección destino - esto causará error
        
        $subject = Subject::factory()->create();
        ClassSchedule::factory()->create([
            'section_id' => $currentSection->id,
            'subject_id' => $subject->id,
        ]);
        
        $estudiante = User::factory()->create([
            'role_id' => $estudianteRole->id,
            'section_id' => $currentSection->id,
            'activo' => true
        ]);
        
        StudentCourse::factory()->create([
            'student_id' => $estudiante->id,
            'subject_id' => $subject->id,
            'section_id' => $currentSection->id,
            'school_year_id' => $currentYear->id,
            'status' => 'concluido',
            'closed_at' => now(),
        ]);
        
        $service = new PromotionService();
        $result = $service->promoteSection($currentSection->id, $nextGrade->id, 'promote');
        
        // No debe lanzar excepción - debe retornar estructura
        expect($result)->toHaveKey('promoted');
        expect($result)->toHaveKey('skipped');
        expect($result)->toHaveKey('details');
    });
});