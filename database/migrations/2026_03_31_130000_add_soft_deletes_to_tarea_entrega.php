<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agrega soft deletes a las tablas tareas y entregas.
     * Esto permite recuperación de datos eliminados y mantiene integridad
     * cuando se elimina una tarea (las entregas relacionadas se preservan).
     */
    public function up(): void
    {
        // Agregar deleted_at a tareas
        Schema::table('tareas', function (Blueprint $table) {
            $table->softDeletes();
        });
        echo "✅ Soft deletes added to tareas table" . PHP_EOL;

        // Agregar deleted_at a entregas
        Schema::table('entregas', function (Blueprint $table) {
            $table->softDeletes();
        });
        echo "✅ Soft deletes added to entregas table" . PHP_EOL;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        echo "✅ Soft deletes removed from tareas table" . PHP_EOL;

        Schema::table('entregas', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        echo "✅ Soft deletes removed from entregas table" . PHP_EOL;
    }
};
