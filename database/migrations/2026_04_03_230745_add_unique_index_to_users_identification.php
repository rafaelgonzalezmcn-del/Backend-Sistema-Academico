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
     * F9-EX4: Índice único en identification_number para garantizar integridad real.
     * Usa índice parcial (WHERE deleted_at IS NULL) para permitir duplicados en soft-deleted.
     */
    public function up(): void
    {
        // Primero limpiar duplicados existentes (si los hay)
        // Mantener el registro más reciente, eliminar los demás
        DB::statement("
            DELETE FROM users a USING users b
            WHERE a.id < b.id
              AND a.identification_number IS NOT NULL
              AND a.identification_number = b.identification_number
              AND a.identification_number != ''
        ");

        Schema::table('users', function (Blueprint $table) {
            $table->unique('identification_number', 'users_identification_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_identification_unique');
        });
    }
};
