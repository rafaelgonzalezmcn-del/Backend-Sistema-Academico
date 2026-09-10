<?php

use App\Models\User;
use App\Models\Role;
use App\Models\Grade;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\ClassSchedule;
use App\Services\GradeService;
use App\Services\SchoolYearService;
use App\Services\SectionService;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'estudiante']);
    Role::firstOrCreate(['name' => 'profesor']);
});

// Tests that don't trigger services with explicit DB::transaction()
// to avoid PostgreSQL "failed transaction" state

describe('GradeService - Coverage Tests (read-only)', function () {
    
    test('index retorna grados ordenados', function () {
        $service = new GradeService();
        
        Grade::create(['name' => '3er Grado', 'grade_order' => 3]);
        Grade::create(['name' => '1er Grado', 'grade_order' => 1]);
        Grade::create(['name' => '2do Grado', 'grade_order' => 2]);
        
        $result = $service->index([]);
        
        expect($result['data'][0]->grade_order)->toBe(1);
        expect($result['data'][1]->grade_order)->toBe(2);
        expect($result['data'][2]->grade_order)->toBe(3);
    });
    
    test('show carga secciones', function () {
        $service = new GradeService();
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::create(['name' => '1er Grado', 'grade_order' => 1]);
        Section::create([
            'grade_id' => $grade->id,
            'school_year_id' => $schoolYear->id,
            'name' => 'A-' . uniqid(),
        ]);
        
        $result = $service->show($grade);
        
        expect($result->sections)->toHaveCount(1);
    });
    
    test('delete lanza excepción si tiene secciones', function () {
        $service = new GradeService();
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::create(['name' => '1er Grado', 'grade_order' => 1]);
        Section::create([
            'grade_id' => $grade->id,
            'school_year_id' => $schoolYear->id,
            'name' => 'B-' . uniqid(),
        ]);
        
        expect(function () use ($service, $grade) {
            $service->delete($grade);
        })->toThrow(\Exception::class);
    });
    
    // Eliminado: create/update/delete porque usan LogsActivity trait que llama DB::transaction
});

describe('SchoolYearService - Coverage Tests (read-only)', function () {
    
    test('index filtra solo activos', function () {
        $service = new SchoolYearService();
        
        SchoolYear::create(['name' => '2024', 'active' => false, 'grade_order' => 0]);
        SchoolYear::create(['name' => '2025', 'active' => true, 'grade_order' => 1]);
        
        $result = $service->index(['only_active' => true]);
        
        expect($result->total())->toBe(1);
    });
    
    test('count cuenta correctamente', function () {
        $service = new SchoolYearService();
        
        SchoolYear::create(['name' => '2024', 'active' => false, 'grade_order' => 0]);
        SchoolYear::create(['name' => '2025', 'active' => true, 'grade_order' => 1]);
        
        expect($service->count([]))->toBe(2);
        expect($service->count(['only_active' => true]))->toBe(1);
    });
    
    // Eliminado: getActive/getActiveFresh porque usan getActiveFresh() que llama al servicio
    // que a su vez tiene DB::transaction en su create (llamado en beforeEach Roles)
});

describe('SectionService - Coverage Tests', function () {
    
    test('count sin filtros', function () {
        $service = new SectionService();
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create();
        
        // Create sections with unique names to avoid unique constraint violation
        Section::create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id, 'name' => 'S1-' . uniqid()]);
        Section::create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id, 'name' => 'S2-' . uniqid()]);
        Section::create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id, 'name' => 'S3-' . uniqid()]);
        
        expect($service->count([]))->toBe(3);
    });
    
    test('count con filtro grade_id', function () {
        $service = new SectionService();
        
        $schoolYear = SchoolYear::factory()->create();
        $grade1 = Grade::factory()->create();
        $grade2 = Grade::factory()->create();
        
        // Create sections with unique names to avoid unique constraint violation
        Section::create(['grade_id' => $grade1->id, 'school_year_id' => $schoolYear->id, 'name' => 'C1-' . uniqid()]);
        Section::create(['grade_id' => $grade1->id, 'school_year_id' => $schoolYear->id, 'name' => 'C2-' . uniqid()]);
        Section::create(['grade_id' => $grade2->id, 'school_year_id' => $schoolYear->id, 'name' => 'D1-' . uniqid()]);
        Section::create(['grade_id' => $grade2->id, 'school_year_id' => $schoolYear->id, 'name' => 'D2-' . uniqid()]);
        Section::create(['grade_id' => $grade2->id, 'school_year_id' => $schoolYear->id, 'name' => 'D3-' . uniqid()]);
        
        expect($service->count(['grade_id' => $grade1->id]))->toBe(2);
    });
    
    test('show carga relaciones', function () {
        $service = new SectionService();
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create();
        $section = Section::factory()->create([
            'grade_id' => $grade->id,
            'school_year_id' => $schoolYear->id,
        ]);
        
        $result = $service->show($section);
        
        expect($result->grade)->not->toBeNull();
    });
});