<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega columna `max_capacity` a sections para controlar cupos.
     * - Nullable: significa "capacidad ilimitada" (sin restricción)
     * - Index: para optimizar consultas de secciones llenas
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->unsignedInteger('max_capacity')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn('max_capacity');
        });
    }
};
