<?php

namespace App\Support;

/**
 * Tamaño de página seguro para los listados.
 *
 * Antes se aceptaba cualquier valor (incluso -1 = "todos"), lo que permitía
 * pedir miles de registros en una sola petición. El frontend usa como máximo 100.
 */
class Paginacion
{
    public const MAXIMO = 100;

    public static function porPagina(mixed $valor, int $porDefecto = 15): int
    {
        $n = (int) $valor;

        if ($n < 1) {
            return $porDefecto;
        }

        return min($n, self::MAXIMO);
    }
}
