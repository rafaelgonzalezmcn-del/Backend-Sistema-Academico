<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Mueve los archivos subidos antes de la migración a disco privado
| (storage/app/public/{materiales,tareas,entregas}) a storage/app/private.
| Las rutas guardadas en la base de datos no cambian.
*/
Artisan::command('archivos:privatizar', function () {
    $publico = \Illuminate\Support\Facades\Storage::disk(\App\Support\ArchivoPrivado::DISCO_ANTIGUO);
    $privado = \Illuminate\Support\Facades\Storage::disk(\App\Support\ArchivoPrivado::DISCO);
    $movidos = 0;

    foreach (\App\Support\ArchivoPrivado::CARPETAS as $carpeta) {
        foreach ($publico->allFiles($carpeta) as $ruta) {
            if (!$privado->exists($ruta)) {
                $privado->writeStream($ruta, $publico->readStream($ruta));
            }
            $publico->delete($ruta);
            $movidos++;
            $this->line("Movido: {$ruta}");
        }
    }

    $this->info("Listo: {$movidos} archivo(s) movidos al disco privado.");
})->purpose('Mover archivos de materiales, tareas y entregas al disco privado');
