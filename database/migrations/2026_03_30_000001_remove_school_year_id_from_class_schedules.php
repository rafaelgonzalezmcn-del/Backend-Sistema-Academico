<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // VERIFICACIÓN:确保 no hay inconsistencias antes de eliminar
        $inconsistentes = DB::select("
            SELECT COUNT(*) as count
            FROM class_schedules cs
            JOIN sections s ON cs.section_id = s.id
            WHERE cs.school_year_id IS NOT NULL 
              AND s.school_year_id IS NOT NULL
              AND cs.school_year_id != s.school_year_id
        ");

        if ($inconsistentes[0]->count > 0) {
            throw new \Exception(
                "Hay {$inconsistentes[0]->count} horarios con año lectivo inconsistente. "
                . "Corrígelos antes de ejecutar esta migración."
            );
        }

        echo "✅ Verificación passed: 0 inconsistencias" . PHP_EOL;

        // Verificar que TODOS los schedules tienen un school_year_id (no pueden ser null)
        $sinYear = DB::table('class_schedules')
            ->whereNull('school_year_id')
            ->count();

        if ($sinYear > 0) {
            throw new \Exception(
                "Hay $sinYear horarios sin año lectivo. "
                . "Todos deben tener un año lectivo asignado."
            );
        }

        echo "✅ Verificación passed: todos los horarios tienen año lectivo" . PHP_EOL;

        // Eliminar la columna school_year_id
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropForeign(['school_year_id']);
            $table->dropColumn('school_year_id');
        });

        echo "✅ Columna school_year_id eliminada de class_schedules" . PHP_EOL;
    }

    public function down(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->foreignId('school_year_id')
                ->nullable()
                ->constrained('school_years')
                ->nullOnDelete();
        });
    }
};
