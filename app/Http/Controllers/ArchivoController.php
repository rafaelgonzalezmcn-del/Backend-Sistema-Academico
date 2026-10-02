<?php

namespace App\Http\Controllers;

use App\Support\ArchivoPrivado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Entrega archivos privados a partir de una URL firmada.
 *
 * La firma la genera el backend (ArchivoPrivado::url) solo después de
 * comprobar permisos, y el middleware "signed" rechaza cualquier URL
 * modificada o vencida. Por eso esta ruta no necesita token Bearer:
 * funciona al abrirla en una pestaña nueva o dentro del visor de PDF.
 */
class ArchivoController extends Controller
{
    public function ver(Request $request)
    {
        $ruta = (string) $request->query('ruta', '');
        $disco = ArchivoPrivado::disco($ruta);

        if (!$disco) {
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }

        // "inline" permite mostrar PDFs en el navegador; el resto se descarga.
        return Storage::disk($disco)->response($ruta, basename($ruta), [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
