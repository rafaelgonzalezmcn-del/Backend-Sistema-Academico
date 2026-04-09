<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agregar schedule para que el profesor (user_id=7) pueda enseñar la materia_id=1
     */
    public function up(): void
    {
        // Agregar un schedule para el profesor 7 en la materia 1 (para que pueda crear tareas en módulos de esa materia)
        // Usando section_id=2 (Sección D) y horario diferente al existente para evitar conflicto
        DB::table('class_schedules')->insert([
            'teacher_id' => 7,
            'subject_id' => 1,
            'section_id' => 2,
            'day' => 'Martes',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('class_schedules')
            ->where('teacher_id', 7)
            ->where('subject_id', 1)
            ->where('day', 'Martes')
            ->delete();
    }
};