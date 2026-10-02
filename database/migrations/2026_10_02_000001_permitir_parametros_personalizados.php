<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permite que un parcial tenga parámetros personalizados (tipo "otro").
 *
 * Antes el índice único (parcial_id, tipo) impedía crear cualquier parámetro
 * nuevo, porque cada parcial ya trae los 4 tipos estándar por defecto.
 * Ahora la unicidad solo aplica a los tipos estándar; los personalizados
 * se distinguen por su nombre (validado en StoreParametroRequest).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS parametros_parcial_tipo_unique');
        DB::statement("
            CREATE UNIQUE INDEX parametros_parcial_tipo_unique
            ON parametros (parcial_id, tipo)
            WHERE tipo <> 'otro'
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS parametros_parcial_tipo_unique');
        DB::statement('CREATE UNIQUE INDEX parametros_parcial_tipo_unique ON parametros (parcial_id, tipo)');
    }
};
