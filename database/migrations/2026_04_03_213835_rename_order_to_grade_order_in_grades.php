<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Renombra la columna `order` → `grade_order` porque `order` es palabra
     * reservada en SQL y causa conflictos de sintaxis en PostgreSQL.
     */
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->renameColumn('order', 'grade_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->renameColumn('grade_order', 'order');
        });
    }
};
