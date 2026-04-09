<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agrega restricciones de unicidad para prevenir datos duplicados:
     * - modulos: (materia_id, nombre) - una materia no puede tener dos módulos con el mismo nombre
     * - parciales: (modulo_id, numero) - un módulo no puede tener dos parciales con el mismo número
     * - parametros: (parcial_id, tipo) - un parcial no puede tener dos parámetros del mismo tipo
     */
    public function up(): void
    {
        // 1. Unique constraint para modulos (materia_id, nombre)
        // Solo aplica whereNull('deleted_at') para permitir recuperación con soft deletes
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS modulos_materia_nombre_unique 
            ON modulos (materia_id, nombre) 
            WHERE deleted_at IS NULL
        ");
        echo "✅ Unique constraint added: modulos (materia_id, nombre)" . PHP_EOL;

        // 2. Unique constraint para parciales (modulo_id, numero)
        // parciales NO tiene soft deletes
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS parciales_modulo_numero_unique 
            ON parciales (modulo_id, numero)
        ");
        echo "✅ Unique constraint added: parciales (modulo_id, numero)" . PHP_EOL;

        // 3. Unique constraint para parametros (parcial_id, tipo)
        // parametros NO tiene soft deletes
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS parametros_parcial_tipo_unique 
            ON parametros (parcial_id, tipo)
        ");
        echo "✅ Unique constraint added: parametros (parcial_id, tipo)" . PHP_EOL;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS modulos_materia_nombre_unique");
        echo "✅ Unique constraint removed: modulos" . PHP_EOL;

        DB::statement("DROP INDEX IF EXISTS parciales_modulo_numero_unique");
        echo "✅ Unique constraint removed: parciales" . PHP_EOL;

        DB::statement("DROP INDEX IF EXISTS parametros_parcial_tipo_unique");
        echo "✅ Unique constraint removed: parametros" . PHP_EOL;
    }
};
