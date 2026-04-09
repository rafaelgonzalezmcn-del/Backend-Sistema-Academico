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
        // Tabla de parciales
        Schema::create('parciales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->integer('numero'); // 1, 2, 3...
            $table->foreignId('modulo_id')->constrained('modulos')->onDelete('cascade');
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->timestamps();
        });

        // Tabla de parámetros
        Schema::create('parametros', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('tipo'); // actividades_clase, tareas, actuacion, examenes
            $table->foreignId('parcial_id')->constrained('parciales')->onDelete('cascade');
            $table->decimal('porcentaje', 5, 2)->default(0); // Porcentaje del parcial
            $table->integer('nota_maxima_default')->default(100); // Nota máxima por defecto
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parametros');
        Schema::dropIfExists('parciales');
    }
};
