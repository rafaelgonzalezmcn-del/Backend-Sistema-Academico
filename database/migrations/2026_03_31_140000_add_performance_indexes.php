<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agrega índices para optimizar queries frecuentes:
     * - tareas: modulo_id, parcial_id, parametro_id
     * - entregas: estudiante_id (para consultas por estudiante)
     * - parciales: modulo_id
     * - parametros: parcial_id
     * - materiales: modulo_id
     */
    public function up(): void
    {
        // Índices para tareas
        Schema::table('tareas', function (Blueprint $table) {
            $table->index('modulo_id', 'idx_tareas_modulo');
            $table->index('parcial_id', 'idx_tareas_parcial');
            $table->index('parametro_id', 'idx_tareas_parametro');
            $table->index('fecha_limite', 'idx_tareas_fecha_limite');
        });
        echo "✅ Indexes added to tareas table" . PHP_EOL;

        // Índices para entregas
        Schema::table('entregas', function (Blueprint $table) {
            $table->index('estudiante_id', 'idx_entregas_estudiante');
            // tarea_id ya tiene índice único compuesto
        });
        echo "✅ Indexes added to entregas table" . PHP_EOL;

        // Índice para parciales
        Schema::table('parciales', function (Blueprint $table) {
            $table->index('modulo_id', 'idx_parciales_modulo');
        });
        echo "✅ Index added to parciales table" . PHP_EOL;

        // Índice para parametros
        Schema::table('parametros', function (Blueprint $table) {
            $table->index('parcial_id', 'idx_parametros_parcial');
        });
        echo "✅ Index added to parametros table" . PHP_EOL;

        // Índice para materiales
        Schema::table('materiales', function (Blueprint $table) {
            $table->index('modulo_id', 'idx_materiales_modulo');
        });
        echo "✅ Index added to materiales table" . PHP_EOL;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropIndex('idx_tareas_modulo');
            $table->dropIndex('idx_tareas_parcial');
            $table->dropIndex('idx_tareas_parametro');
            $table->dropIndex('idx_tareas_fecha_limite');
        });
        echo "✅ Indexes removed from tareas table" . PHP_EOL;

        Schema::table('entregas', function (Blueprint $table) {
            $table->dropIndex('idx_entregas_estudiante');
        });
        echo "✅ Index removed from entregas table" . PHP_EOL;

        Schema::table('parciales', function (Blueprint $table) {
            $table->dropIndex('idx_parciales_modulo');
        });
        echo "✅ Index removed from parciales table" . PHP_EOL;

        Schema::table('parametros', function (Blueprint $table) {
            $table->dropIndex('idx_parametros_parcial');
        });
        echo "✅ Index removed from parametros table" . PHP_EOL;

        Schema::table('materiales', function (Blueprint $table) {
            $table->dropIndex('idx_materiales_modulo');
        });
        echo "✅ Index removed from materiales table" . PHP_EOL;
    }
};
