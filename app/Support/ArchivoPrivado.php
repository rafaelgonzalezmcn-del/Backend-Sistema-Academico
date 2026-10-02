<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Manejo centralizado de archivos subidos (materiales, tareas y entregas).
 *
 * Los archivos se guardan en el disco privado "local" (storage/app/private),
 * que NO es accesible desde el navegador. Para descargarlos se genera una
 * URL firmada y temporal (expira en pocos minutos) que solo se entrega a
 * usuarios que ya pasaron la verificación de permisos (Policies/servicios).
 *
 * Compatibilidad: los archivos antiguos que siguen en el disco "public" se
 * siguen encontrando. Para moverlos al disco privado, ejecutar:
 *   php artisan archivos:privatizar
 */
class ArchivoPrivado
{
    public const DISCO = 'local';
    public const DISCO_ANTIGUO = 'public';

    /** Carpetas permitidas: evita que se pida cualquier archivo del servidor. */
    public const CARPETAS = ['materiales', 'tareas', 'entregas'];

    /** Minutos de validez de las URLs de descarga. */
    public const MINUTOS_VALIDEZ = 60;

    /**
     * Guarda el archivo en el disco privado y devuelve su ruta relativa.
     * El nombre incluye un fragmento aleatorio para que no sea adivinable.
     */
    public static function guardar(UploadedFile $file, string $carpeta): string
    {
        $original = $file->getClientOriginalName();
        $limpio = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original);
        $nombre = time() . '_' . Str::random(10) . '_' . $limpio;

        return $file->storeAs($carpeta, $nombre, self::DISCO);
    }

    /** Devuelve el disco donde está el archivo, o null si no existe. */
    public static function disco(?string $ruta): ?string
    {
        if (!$ruta || !self::rutaPermitida($ruta)) {
            return null;
        }
        foreach ([self::DISCO, self::DISCO_ANTIGUO] as $disco) {
            if (Storage::disk($disco)->exists($ruta)) {
                return $disco;
            }
        }
        return null;
    }

    public static function existe(?string $ruta): bool
    {
        return self::disco($ruta) !== null;
    }

    public static function eliminar(?string $ruta): void
    {
        if (!$ruta) {
            return;
        }
        foreach ([self::DISCO, self::DISCO_ANTIGUO] as $disco) {
            if (Storage::disk($disco)->exists($ruta)) {
                Storage::disk($disco)->delete($ruta);
            }
        }
    }

    /** URL firmada y temporal para ver/descargar el archivo. */
    public static function url(?string $ruta): ?string
    {
        if (!$ruta) {
            return null;
        }

        return URL::temporarySignedRoute(
            'archivos.ver',
            now()->addMinutes(self::MINUTOS_VALIDEZ),
            ['ruta' => $ruta]
        );
    }

    /**
     * Solo rutas dentro de las carpetas permitidas y sin "..".
     */
    public static function rutaPermitida(string $ruta): bool
    {
        if (str_contains($ruta, '..') || str_contains($ruta, '\\') || str_starts_with($ruta, '/')) {
            return false;
        }
        $carpeta = explode('/', $ruta)[0] ?? '';
        return in_array($carpeta, self::CARPETAS, true);
    }
}
