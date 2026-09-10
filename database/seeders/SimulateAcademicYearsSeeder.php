<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class SimulateAcademicYearsSeeder extends Seeder
{
    /**
     * Simulación de 3 años lectivos completos para demostrar el sistema académico.
     * 
     * AÑO 1: 2023-2024 (grade_order: 1)
     * AÑO 2: 2024-2025 (grade_order: 2)
     * AÑO 3: 2025-2026 (grade_order: 3) - año activo
     */
    public function run(): void
    {
        $this->command->info('🎓 Iniciando simulación de 3 años lectivos...');
        
        // ============================================
        // FASE 1: ESTRUCTURA BASE
        // ============================================
        
        $this->command->info('📚 Creando estructura base (años, grados, materias)...');
        
        // Años lectivos
        $years = [
            ['id' => 1, 'name' => '2023-2024', 'grade_order' => 1, 'start_date' => '2023-03-01', 'end_date' => '2023-12-15', 'active' => false],
            ['id' => 2, 'name' => '2024-2025', 'grade_order' => 2, 'start_date' => '2024-03-01', 'end_date' => '2024-12-15', 'active' => false],
            ['id' => 3, 'name' => '2025-2026', 'grade_order' => 3, 'start_date' => '2025-03-01', 'end_date' => '2025-12-15', 'active' => true],
        ];
        
        foreach ($years as $year) {
            DB::table('school_years')->updateOrInsert(['id' => $year['id']], $year);
        }
        
        // Grados con orden
        $grades = [
            ['id' => 1, 'name' => 'Primero', 'grade_order' => 1],
            ['id' => 2, 'name' => 'Segundo', 'grade_order' => 2],
            ['id' => 3, 'name' => 'Tercero', 'grade_order' => 3],
            ['id' => 4, 'name' => 'Cuarto', 'grade_order' => 4],
            ['id' => 5, 'name' => 'Quinto', 'grade_order' => 5],
        ];
        
        foreach ($grades as $grade) {
            DB::table('grades')->updateOrInsert(['id' => $grade['id']], $grade);
        }
        
        // Materias por grado (materias_base para todos los grados)
        $subjectsData = [
            ['id' => 1, 'name' => 'Matemática'],
            ['id' => 2, 'name' => 'Lenguaje'],
            ['id' => 3, 'name' => 'Ciencias'],
            ['id' => 4, 'name' => 'Estudios Sociales'],
            ['id' => 5, 'name' => 'Educación Física'],
        ];
        
        foreach ($subjectsData as $subject) {
            DB::table('subjects')->updateOrInsert(['id' => $subject['id']], $subject);
        }
        
        // ============================================
        // FASE 2: PROFESORES
        // ============================================
        
        $this->command->info('👨‍🏫 Creando profesores...');
        
        $profesorRoleId = DB::table('roles')->where('name', 'profesor')->value('id');
        
        $profesores = [];
        $profesorNames = [
            ['first_name' => 'Carlos', 'last_name' => 'Mendoza'],
            ['first_name' => 'Ana', 'last_name' => 'López'],
            ['first_name' => 'Roberto', 'last_name' => 'Sánchez'],
            ['first_name' => 'María', 'last_name' => 'Fernández'],
            ['first_name' => 'Luis', 'last_name' => 'García'],
        ];
        
        foreach ($profesorNames as $index => $prof) {
            $id = 10 + $index;
            DB::table('users')->updateOrInsert(['id' => $id], [
                'first_name' => $prof['first_name'],
                'last_name' => $prof['last_name'],
                'email' => strtolower($prof['first_name']) . '.' . strtolower($prof['last_name']) . '@profesor.edu',
                'password' => Hash::make('password'),
                'identification_number' => 'P' . str_pad($id, 5, '0', STR_PAD_LEFT),
                'role_id' => $profesorRoleId,
                'activo' => true,
            ]);
            $profesores[] = $id;
        }
        
        // ============================================
        // FASE 3: CREAR SECCIONES PARA CADA AÑO
        // ============================================
        
        $this->command->info('🏫 Creando secciones por año lectivo...');
        
        $sectionId = 1;
        
        // AÑO 1: 2023-2024 (5 grados x 2 secciones = 10 secciones)
        for ($gradeId = 1; $gradeId <= 5; $gradeId++) {
            for ($sec = 1; $sec <= 2; $sec++) {
                DB::table('sections')->updateOrInsert(['id' => $sectionId], [
                    'grade_id' => $gradeId,
                    'school_year_id' => 1,
                    'name' => 'Sección ' . chr(64 + $sec),
                    'max_capacity' => 35,
                ]);
                $sectionId++;
            }
        }
        
        // AÑO 2: 2024-2025 (5 grados x 2 secciones = 10 secciones)
        for ($gradeId = 1; $gradeId <= 5; $gradeId++) {
            for ($sec = 1; $sec <= 2; $sec++) {
                DB::table('sections')->updateOrInsert(['id' => $sectionId], [
                    'grade_id' => $gradeId,
                    'school_year_id' => 2,
                    'name' => 'Sección ' . chr(64 + $sec),
                    'max_capacity' => 35,
                ]);
                $sectionId++;
            }
        }
        
        // AÑO 3: 2025-2026 (5 grados x 2 secciones = 10 secciones)
        for ($gradeId = 1; $gradeId <= 5; $gradeId++) {
            for ($sec = 1; $sec <= 2; $sec++) {
                DB::table('sections')->updateOrInsert(['id' => $sectionId], [
                    'grade_id' => $gradeId,
                    'school_year_id' => 3,
                    'name' => 'Sección ' . chr(64 + $sec),
                    'max_capacity' => 35,
                ]);
                $sectionId++;
            }
        }
        
        $totalSections = $sectionId - 1;
        $this->command->info("   Creadas {$totalSections} secciones totales");
        
        // ============================================
        // FASE 4: HORARIOS (ClassSchedules) - Materias por sección
        // ============================================
        
        $this->command->info('📅 Creando horarios (materias por sección)...');
        
        $scheduleId = 1;
        
        // Mapas de secciones por año
        // Año 1: secciones 1-10 (grade 1: 1-2, grade 2: 3-4, ..., grade 5: 9-10)
        // Año 2: secciones 11-20
        // Año 3: secciones 21-30
        
        for ($yearId = 1; $yearId <= 3; $yearId++) {
            $startSection = ($yearId - 1) * 10 + 1;
            $endSection = $yearId * 10;
            
            for ($sectionId = $startSection; $sectionId <= $endSection; $sectionId++) {
                $gradeId = floor(($sectionId - $startSection) / 2) + 1;
                
                // Asignar 3-4 materias a cada sección
                $subjectsForSection = array_slice([1, 2, 3, 4, 5], 0, 4);
                
                foreach ($subjectsForSection as $subjectIndex => $subjectId) {
                    DB::table('class_schedules')->updateOrInsert(['id' => $scheduleId], [
                        'section_id' => $sectionId,
                        'subject_id' => $subjectId,
                        'teacher_id' => $profesores[($gradeId - 1 + $subjectIndex) % count($profesores)],
                        'day' => ($subjectIndex + 1),
                        'start_time' => sprintf('%02d:00', 7 + $subjectIndex),
                        'end_time' => sprintf('%02d:00', 8 + $subjectIndex),
                    ]);
                    $scheduleId++;
                }
            }
        }
        
        $totalSchedules = $scheduleId - 1;
        $this->command->info("   Creados {$totalSchedules} horarios");
        
        // ============================================
        // FASE 5: ESTUDIANTES
        // ============================================
        
        $this->command->info('🎓 Creando estudiantes...');
        
        $estudianteRoleId = DB::table('roles')->where('name', 'estudiante')->value('id');
        
        $students = [];
        $studentId = 100;
        
        // Crear estudiantes para año 1 (20 estudiantes por sección = 200 estudiantes)
        // Algunos aprobados pasan a año 2, otros repiten
        // Año 2: nuevos ingresos + promovidos
        // Año 3: nuevos ingresos + promovidos
        
        // === AÑO 1 (2023-2024): Estudiantes en secciones 1-10 ===
        $studentsYear1 = [];
        
        for ($sectionId = 1; $sectionId <= 10; $sectionId++) {
            $studentsInSection = [];
            for ($i = 0; $i < 20; $i++) {
                DB::table('users')->updateOrInsert(['id' => $studentId], [
                    'first_name' => 'Estudiante',
                    'last_name' => sprintf('%03d', $studentId),
                    'email' => 'est' . $studentId . '@est.edu',
                    'password' => Hash::make('password'),
                    'identification_number' => 'E' . str_pad($studentId, 6, '0', STR_PAD_LEFT),
                    'role_id' => $estudianteRoleId,
                    'section_id' => $sectionId,
                    'activo' => true,
                ]);
                $studentsInSection[] = $studentId;
                $studentsYear1[] = $studentId;
                $studentId++;
            }
            $this->command->info("   Sección $sectionId: " . count($studentsInSection) . " estudiantes");
        }
        
        $this->command->info("   ✓ Año 1 (2023-2024): " . count($studentsYear1) . " estudiantes");
        
        // === AÑO 2 (2024-2025): promociones + nuevos ===
        
        // Algunos estudiantes del año 1 son promovidos al año 2 (80%)
        // Algunos repiten (20%)
        
        $promotedToYear2 = [];
        $repeatYear1 = [];
        
        $chunks = array_chunk($studentsYear1, 20); // 10 chunks de 20 (secciones)
        
        foreach ($chunks as $sectionIndex => $sectionStudents) {
            $gradeId = $sectionIndex + 1; // 1-5
            
            // 80% promoted, 20% repeat
            $promoteCount = (int)(count($sectionStudents) * 0.8);
            $sectionPromoted = array_slice($sectionStudents, 0, $promoteCount);
            $sectionRepeat = array_slice($sectionStudents, $promoteCount);
            
            // Los promoted van al siguiente grado en año 2
            if ($gradeId < 5) {
                $nextGradeId = $gradeId + 1;
                // Sección destino: año 2, siguiente grado, misma letra de sección
                $targetSection = ($nextGradeId - 1) * 2 * 10 + 11 + (($sectionIndex % 2) * 10); // math magic
                $targetSection = 10 + ($nextGradeId - 1) * 2 + ($sectionIndex % 2) + 1;
            }
            
            $promotedToYear2 = array_merge($promotedToYear2, $sectionPromoted);
            $repeatYear1 = array_merge($repeatYear1, $sectionRepeat);
        }
        
        $this->command->info("   → Promovidos a año 2: " . count($promotedToYear2));
        $this->command->info("   → Repiten año 1: " . count($repeatYear1));
        
        // Actualizar-repeat año 1
        foreach ($repeatYear1 as $sid) {
            DB::table('users')->where('id', $sid)->update(['section_id' => rand(1, 10)]);
        }
        
        // Estudiantes promovidos al año 2 (siguiente grado)
        foreach ($promotedToYear2 as $sid) {
            $currentSection = DB::table('users')->where('id', $sid)->value('section_id');
            $currentGrade = floor(($currentSection - 1) / 2) + 1;
            
            if ($currentGrade < 5) {
                $nextGrade = $currentGrade + 1;
                // Map to year 2 sections: 11-20
                $year2SectionOffset = ($nextGrade - 1) * 2;
                $sectionLetter = ($currentSection % 2) + 1;
                $newSectionId = 11 + $year2SectionOffset + ($sectionLetter - 1);
                
                DB::table('users')->where('id', $sid)->update(['section_id' => $newSectionId]);
            }
        }
        
        // Nuevos estudiantes para año 2 (llenar secciones que no tienen suficientes)
        for ($sectionId = 11; $sectionId <= 20; $sectionId++) {
            $currentCount = DB::table('users')->where('section_id', $sectionId)->where('role_id', $estudianteRoleId)->count();
            $needed = 20 - $currentCount;
            
            for ($i = 0; $i < $needed; $i++) {
                DB::table('users')->updateOrInsert(['id' => $studentId], [
                    'first_name' => 'Estudiante',
                    'last_name' => sprintf('%03d', $studentId),
                    'email' => 'est' . $studentId . '@est.edu',
                    'password' => Hash::make('password'),
                    'identification_number' => 'E' . str_pad($studentId, 6, '0', STR_PAD_LEFT),
                    'role_id' => $estudianteRoleId,
                    'section_id' => $sectionId,
                    'activo' => true,
                ]);
                $studentId++;
            }
        }
        
        $year2Students = DB::table('users')
            ->where('role_id', $estudianteRoleId)
            ->whereBetween('section_id', [11, 20])
            ->pluck('id')
            ->toArray();
        
        $this->command->info("   ✓ Año 2 (2024-2025): " . count($year2Students) . " estudiantes");
        
        // === AÑO 3 (2025-2026): promociones ===
        
        // Promover estudiantes de año 2 a año 3
        $promotedToYear3 = [];
        
        $year2Chunks = array_chunk($year2Students, 20);
        
        foreach ($year2Chunks as $sectionIndex => $sectionStudents) {
            $gradeId = $sectionIndex + 1; // 1-5
            
            // 85% promoted
            $promoteCount = (int)(count($sectionStudents) * 0.85);
            $sectionPromoted = array_slice($sectionStudents, 0, $promoteCount);
            
            $promotedToYear3 = array_merge($promotedToYear3, $sectionPromoted);
        }
        
        $this->command->info("   → Promovidos a año 3: " . count($promotedToYear3));
        
        // Mover a año 3 (siguiente grado)
        foreach ($promotedToYear3 as $sid) {
            $currentSection = DB::table('users')->where('id', $sid)->value('section_id');
            $currentGrade = 1 + floor(($currentSection - 11) / 2);
            
            if ($currentGrade < 5) {
                $nextGrade = $currentGrade + 1;
                $sectionLetter = (($currentSection - 11) % 2) + 1;
                $newSectionId = 21 + ($nextGrade - 1) * 2 + ($sectionLetter - 1);
                
                DB::table('users')->where('id', $sid)->update(['section_id' => $newSectionId]);
            }
        }
        
        // Nuevos estudiantes para año 3
        for ($sectionId = 21; $sectionId <= 30; $sectionId++) {
            $currentCount = DB::table('users')->where('section_id', $sectionId)->where('role_id', $estudianteRoleId)->count();
            $needed = 20 - $currentCount;
            
            for ($i = 0; $i < $needed; $i++) {
                DB::table('users')->updateOrInsert(['id' => $studentId], [
                    'first_name' => 'Estudiante',
                    'last_name' => sprintf('%03d', $studentId),
                    'email' => 'est' . $studentId . '@est.edu',
                    'password' => Hash::make('password'),
                    'identification_number' => 'E' . str_pad($studentId, 6, '0', STR_PAD_LEFT),
                    'role_id' => $estudianteRoleId,
                    'section_id' => $sectionId,
                    'activo' => true,
                ]);
                $studentId++;
            }
        }
        
        $year3Students = DB::table('users')
            ->where('role_id', $estudianteRoleId)
            ->whereBetween('section_id', [21, 30])
            ->pluck('id')
            ->toArray();
        
        $this->command->info("   ✓ Año 3 (2025-2026): " . count($year3Students) . " estudiantes");
        
        // ============================================
        // FASE 6: MÓDULOS, PARCIALES, TAREAS
        // ============================================
        
        $this->command->info('📝 Creando módulos, parciales y tareas...');
        
        $moduloId = 1;
        $parcialId = 1;
        $tareaId = 1;
        
        // Por cada materia (1-5), crear módulos y tareas
        for ($subjectId = 1; $subjectId <= 5; $subjectId++) {
            // 3 módulos por materia
            for ($m = 1; $m <= 3; $m++) {
                $moduloId = DB::table('modulos')->insertGetId([
                    'nombre' => 'Módulo ' . $m . ' - Materia ' . $subjectId,
                    'descripcion' => 'Unidad ' . $m . ' del curso',
                    'materia_id' => $subjectId,
                ]);
                
                // 2 parciales por módulo
                for ($p = 1; $p <= 2; $p++) {
                    $parcialId = DB::table('parciales')->insertGetId([
                        'nombre' => 'Parcial ' . $p,
                        'numero' => $p,
                        'modulo_id' => $moduloId,
                        'nota_maxima' => 100,
                    ]);
                    
                    // 1 tarea por parcial (para cada sección)
                    for ($yearId = 1; $yearId <= 3; $yearId++) {
                        $startSec = ($yearId - 1) * 10 + 1;
                        $endSec = $yearId * 10;
                        
                        for ($secId = $startSec; $secId <= $endSec; $secId++) {
                            // Verificar que la materia esté en esta sección
                            $hasSchedule = DB::table('class_schedules')
                                ->where('section_id', $secId)
                                ->where('subject_id', $subjectId)
                                ->exists();
                            
                            if ($hasSchedule) {
                                $tareaId = DB::table('tareas')->insertGetId([
                                    'titulo' => 'Tarea ' . $p . ' - Módulo ' . $m,
                                    'descripcion' => 'Ejercicios del parcial ' . $p,
                                    'puntaje_maximo' => 10,
                                    'modulo_id' => $moduloId,
                                    'parcial_id' => $parcialId,
                                    'fecha_limite' => now()->addDays(30),
                                    'created_at' => now(),
                                ]);
                            }
                        }
                    }
                }
            }
        }
        
        $this->command->info('   ✓ Creados módulos, parciales y tareas');
        
        // ============================================
        // FASE 7: CALIFICACIONES (variadas)
        // ============================================
        
        $this->command->info('📊 Creando calificaciones con resultados variados...');
        
        // Para cada año lectivo, crear StudentCourse con notas
        // Algunos aprobados, algunos reprobados
        
        for ($yearId = 1; $yearId <= 3; $yearId++) {
            $startSection = ($yearId - 1) * 10 + 1;
            $endSection = $yearId * 10;
            
            $yearStudents = DB::table('users')
                ->where('role_id', $estudianteRoleId)
                ->whereBetween('section_id', [$startSection, $endSection])
                ->pluck('id')
                ->toArray();
            
            $this->command->info("   Procesando año $yearId con " . count($yearStudents) . " estudiantes...");
            
            foreach ($yearStudents as $studentId) {
                // Obtener secciones del estudiante
                $sectionId = DB::table('users')->where('id', $studentId)->value('section_id');
                
                // Obtener materias de esa sección
                $subjectIds = DB::table('class_schedules')
                    ->where('section_id', $sectionId)
                    ->pluck('subject_id')
                    ->toArray();
                
                foreach ($subjectIds as $subjectId) {
                    // Obtener profesor de la materia en esta sección
                    $teacherId = DB::table('class_schedules')
                        ->where('section_id', $sectionId)
                        ->where('subject_id', $subjectId)
                        ->value('teacher_id');
                    
                    // Calcular nota basada en el estudiante
                    //学生们 con ID par tienen mejores notas
                    $baseGrade = ($studentId % 10) * 10 + 50; // 50-140
                    $grade = min(100, $baseGrade);
                    
                    // 70% para aprobar
                    $status = $grade >= 70 ? 'aprobado' : 'reprobado';
                    
                    // Créer StudentCourse
                    DB::table('student_courses')->insert([
                        'student_id' => $studentId,
                        'subject_id' => $subjectId,
                        'section_id' => $sectionId,
                        'school_year_id' => $yearId,
                        'profesor_id' => $teacherId,
                        'final_grade' => $grade,
                        'total_score_obtained' => $grade,
                        'total_score_possible' => 100,
                        'passing_percentage' => 70.00,
                        'status' => $status,
                        'parcial_grades' => json_encode([
                            ['parcial_nombre' => 'Parcial 1', 'nota' => $grade * 0.9, 'nota_maxima' => 100],
                            ['parcial_nombre' => 'Parcial 2', 'nota' => $grade * 1.1, 'nota_maxima' => 100],
                        ]),
                        'closed_at' => now()->subDays(rand(1, 30)),
                        'closed_by_user_id' => 1,
                    ]);
                }
            }
        }
        
        $this->command->info('   ✓ Calificaciones creadas');
        
        // ============================================
        // FASE 8: VERIFICACIÓN FINAL
        // ============================================
        
        $this->command->info('');
        $this->command->info('📊 RESUMEN DE LA SIMULACIÓN:');
        $this->command->info('==========================');
        
        $this->command->info('Años lectivos: ' . DB::table('school_years')->count());
        $this->command->info('Grados: ' . DB::table('grades')->count());
        $this->command->info('Secciones: ' . DB::table('sections')->count());
        $this->command->info('Materias: ' . DB::table('subjects')->count());
        $this->command->info('Usuarios total: ' . DB::table('users')->count());
        $this->command->info('Estudiantes: ' . DB::table('users')->where('role_id', $estudianteRoleId)->count());
        $this->command->info('Profesores: ' . DB::table('users')->where('role_id', $profesorRoleId)->count());
        $this->command->info('Horarios: ' . DB::table('class_schedules')->count());
        $this->command->info('Módulos: ' . DB::table('modulos')->count());
        $this->command->info('Parciales: ' . DB::table('parciales')->count());
        $this->command->info('Tareas: ' . DB::table('tareas')->count());
        $this->command->info('StudentCourses (historial): ' . DB::table('student_courses')->count());
        
        // Estudiantes por año
        $this->command->info('');
        $this->command->info('Estudiantes por año lectivo:');
        for ($yearId = 1; $yearId <= 3; $yearId++) {
            $startSection = ($yearId - 1) * 10 + 1;
            $endSection = $yearId * 10;
            $count = DB::table('users')
                ->where('role_id', $estudianteRoleId)
                ->whereBetween('section_id', [$startSection, $endSection])
                ->count();
            $yearName = DB::table('school_years')->where('id', $yearId)->value('name');
            $this->command->info("  - $yearName: $count estudiantes");
        }
        
        // Cursos por estado
        $this->command->info('');
        $this->command->info('Cursos por estado:');
        $this->command->info('  - Aprobados: ' . DB::table('student_courses')->where('status', 'aprobado')->count());
        $this->command->info('  - Reprobados: ' . DB::table('student_courses')->where('status', 'reprobado')->count());
        
        $this->command->info('');
        $this->command->info('✅ Simulación de 3 años lectivos completada!');
    }
}