<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_courses', function (Blueprint $table) {
            $table->id();
            
            // Relaciones principales
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->foreignId('section_id')->constrained()->onDelete('cascade');
            $table->foreignId('school_year_id')->constrained()->onDelete('cascade');
            
            // Información del curso
            $table->foreignId('profesor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('final_grade', 5, 2)->nullable();
            $table->enum('status', ['cursando', 'aprobado', 'reprobado', 'concluido', 'retirado'])->default('cursando');
            $table->text('observations')->nullable();
            
            // Información de cierre
            $table->date('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            
            // ============================================================
            // UNIQUE CONSTRAINT - SIN SOFTDELETES
            // Garantiza: un estudiante solo puede tener UN registro
            // por materia + sección + año lectivo
            // ============================================================
            $table->unique(
                ['student_id', 'subject_id', 'section_id', 'school_year_id'], 
                'student_courses_unique'
            );
            
            // Índices para consultas frecuentes
            $table->index('student_id', 'student_courses_student_idx');
            $table->index('subject_id', 'student_courses_subject_idx');
            $table->index('status', 'student_courses_status_idx');
            $table->index('school_year_id', 'student_courses_year_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_courses');
    }
};
