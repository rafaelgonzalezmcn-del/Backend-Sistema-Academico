<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Asigna orden secuencial a grados existentes basándose en su nombre.
 * Extrae el número del nombre (ej: "1er Grado" → 1, "2do Grado" → 2).
 */
class PopulateGradeOrdersSeeder extends Seeder
{
    public function run(): void
    {
        $grades = DB::table('grades')->get();

        // Mapa de patrones comunes en nombres de grados
        $patternMap = [
            '1er' => 1, 'primero' => 1, '1ro' => 1, '1°' => 1,
            '2do' => 2, 'segundo' => 2, '2°' => 2,
            '3er' => 3, 'tercero' => 3, '3ro' => 3, '3°' => 3,
            '4to' => 4, 'cuarto' => 4, '4°' => 4,
            '5to' => 5, 'quinto' => 5, '5°' => 5,
            '6to' => 6, 'sexto' => 6, '6°' => 6,
            '7mo' => 7, 'septimo' => 7, 'séptimo' => 7, '7°' => 7,
            '8vo' => 8, 'octavo' => 8, '8°' => 8,
            '9no' => 9, 'noveno' => 9, '9°' => 9,
            '10mo' => 10, 'decimo' => 10, 'décimo' => 10, '10°' => 10,
            '11vo' => 11, '11°' => 11,
            '12vo' => 12, '12°' => 12,
        ];

        $updated = 0;

        foreach ($grades as $grade) {
            // Si ya tiene order, saltar
            if ($grade->grade_order !== null) continue;

            $order = null;
            $nameLower = mb_strtolower($grade->name);

            // Intentar extraer número del nombre
            foreach ($patternMap as $pattern => $num) {
                if (str_contains($nameLower, $pattern)) {
                    $order = $num;
                    break;
                }
            }

            // Fallback: buscar cualquier número en el nombre
            if ($order === null && preg_match('/(\d+)/', $grade->name, $matches)) {
                $order = (int) $matches[1];
            }

            if ($order !== null) {
                DB::table('grades')->where('id', $grade->id)->update(['grade_order' => $order]);
                $updated++;
            }
        }

        $this->command->info("Grados actualizados con order: {$updated}");
    }
}
