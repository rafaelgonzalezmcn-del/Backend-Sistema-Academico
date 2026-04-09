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
            $table->foreignId('parcial_id')->nullable()->constrained('parciales')->onDelete('set null');
            $table->foreignId('parametro_id')->nullable()->constrained('parametros')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropForeign(['parcial_id']);
            $table->dropForeign(['parametro_id']);
            $table->dropColumn(['parcial_id', 'parametro_id']);
        });
    }
};
