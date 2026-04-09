<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Esta migración:
     * 1. Verifica y corrige inconsistencias entre grades y sections
     * 2. Agrega restricción única en sections (grade_id, school_year_id, name)
     * 3. Elimina school_year_id de grades (el año lectivo solo vive en sections)
     */
    public function up(): void
    {
        // ==================== 1. VERIFICAR INCONSISTENCIAS ====================
        
        // Verificar secciones sin año lectivo
        $sectionsWithoutYear = DB::table('sections')
            ->whereNull('school_year_id')
            ->count();
        
        // Verificar grados con año lectivo
        $gradesWithYear = DB::table('grades')
            ->whereNotNull('school_year_id')
            ->count();
        
        echo "Secciones sin año lectivo: {$sectionsWithoutYear}\n";
        echo "Grados con año lectivo: {$gradesWithYear}\n";
        
        // ==================== 2. CORREGIR INCONSISTENCIAS ====================
        
        // 2.1: Secciones sin año pero con grado que SÍ tiene año → heredar del grado
        $toFix1 = DB::table('sections')
            ->join('grades', 'sections.grade_id', '=', 'grades.id')
            ->whereNull('sections.school_year_id')
            ->whereNotNull('grades.school_year_id')
            ->select('sections.id', 'grades.school_year_id as year_to_use')
            ->get();
        
        foreach ($toFix1 as $section) {
            DB::table('sections')
                ->where('id', $section->id)
                ->update(['school_year_id' => $section->year_to_use]);
        }
        echo "Secciones corregidas (heredaron año del grado): " . count($toFix1) . "\n";
        
        // 2.2: Secciones sin año y sin grado con año → buscar año activo más cercano
        $stillWithoutYear = DB::table('sections')
            ->whereNull('school_year_id')
            ->count();
        
        if ($stillWithoutYear > 0) {
            // Obtener el año lectivo activo
            $activeYear = DB::table('school_years')
                ->where('active', true)
                ->first();
            
            if ($activeYear) {
                DB::table('sections')
                    ->whereNull('school_year_id')
                    ->update(['school_year_id' => $activeYear->id]);
                echo "Secciones corregidas (asignado año activo): {$stillWithoutYear}\n";
            } else {
                // Si no hay año activo, tomar el primero disponible
                $firstYear = DB::table('school_years')
                    ->orderBy('start_date', 'asc')
                    ->first();
                
                if ($firstYear) {
                    DB::table('sections')
                        ->whereNull('school_year_id')
                        ->update(['school_year_id' => $firstYear->id]);
                    echo "Secciones corregidas (asignado primer año disponible): {$stillWithoutYear}\n";
                }
            }
        }
        
        // ==================== 3. AGREGAR ÍNDICE ÚNICO ====================
        
        // Verificar si ya existe el índice único para evitar error
        // PostgreSQL usa pg_indexes en lugar de information_schema
        $indexExists = DB::select("
            SELECT COUNT(*) as count 
            FROM pg_indexes 
            WHERE tablename = 'sections' 
            AND indexname = 'sections_grade_year_name_unique'
        ");
        
        if ($indexExists[0]->count == 0) {
            Schema::table('sections', function (Blueprint $table) {
                $table->unique(['grade_id', 'school_year_id', 'name'], 'sections_grade_year_name_unique');
            });
            echo "Índice único agregado a sections: (grade_id, school_year_id, name)\n";
        } else {
            echo "Índice único ya existe, omitiendo...\n";
        }
        
        // ==================== 4. ELIMINAR school_year_id DE GRADES ====================
        
        // Verificar si la columna existe
        if (Schema::hasColumn('grades', 'school_year_id')) {
            Schema::table('grades', function (Blueprint $table) {
                $table->dropForeign(['school_year_id']);
                $table->dropColumn('school_year_id');
            });
            echo "Columna school_year_id eliminada de grades\n";
        } else {
            echo "Columna school_year_id ya no existe en grades\n";
        }
        
        echo "\n✅ Migración completada exitosamente!\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Agregar columna school_year_id a grades
        Schema::table('grades', function (Blueprint $table) {
            $table->foreignId('school_year_id')
                ->nullable()
                ->constrained('school_years')
                ->nullOnDelete();
        });
        
        // 2. Eliminar índice único de sections
        Schema::table('sections', function (Blueprint $table) {
            $table->dropUnique(['grade_id', 'school_year_id', 'name']);
        });
    }
};
