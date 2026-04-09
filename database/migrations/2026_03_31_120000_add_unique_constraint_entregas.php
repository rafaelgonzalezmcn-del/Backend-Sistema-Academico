<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agrega índice único para prevenir entregas duplicadas.
     * IMPORTANTE: Limpia duplicados existentes antes de agregar el constraint.
     */
    public function up(): void
    {
        // Limpiar duplicados existentes - mantener el más reciente (mayor id)
        DB::statement("
            DELETE FROM entregas 
            WHERE id NOT IN (
                SELECT MAX(id) 
                FROM entregas 
                GROUP BY tarea_id, estudiante_id
            )
        ");
        
        echo "✅ Limpiados duplicados en tabla entregas" . PHP_EOL;

        // Agregar unique constraint
        Schema::table('entregas', function (Blueprint $table) {
            $table->unique(['tarea_id', 'estudiante_id'], 'entregas_tarea_estudiante_unique');
        });
        
        echo "✅ Unique constraint added: entregas (tarea_id, estudiante_id)" . PHP_EOL;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->dropUnique('entregas_tarea_estudiante_unique');
        });
        
        echo "✅ Unique constraint removed: entregas" . PHP_EOL;
    }
};
