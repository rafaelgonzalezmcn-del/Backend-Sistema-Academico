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
        Schema::table('tareas', function (Blueprint $table) {
            $table->string('archivo_ruta')->nullable()->after('descripcion');
            $table->string('archivo_nombre')->nullable()->after('archivo_ruta');
            $table->bigInteger('archivo_tamano')->nullable()->after('archivo_nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropColumn(['archivo_ruta', 'archivo_nombre', 'archivo_tamano']);
        });
    }
};
