<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega columna `grade_order` a school_years para definir orden académico correcto.
     * Auto-pobla basado en start_date (más antiguo → menor order).
     */
    public function up(): void
    {
        Schema::table('school_years', function (Blueprint $table) {
            $table->unsignedInteger('grade_order')->nullable()->after('name');
            $table->index('grade_order');
        });

        // Auto-poblar grade_order basado en start_date
        $years = DB::table('school_years')
            ->whereNull('deleted_at')
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        foreach ($years as $index => $year) {
            DB::table('school_years')
                ->where('id', $year->id)
                ->update(['grade_order' => $index + 1]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_years', function (Blueprint $table) {
            $table->dropIndex(['grade_order']);
            $table->dropColumn('grade_order');
        });
    }
};
