<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Documentación Swagger UI - Interfaz visual completa
Route::get('/api-docs', function () {
    return view('swagger');
});

// Documentación Swagger - JSON raw
Route::get('/api-docs-json', function () {
    $docsPath = storage_path('api-docs/openapi.json');
    if (file_exists($docsPath)) {
        return response()->file($docsPath);
    }
    return response()->json(['error' => 'Documentación no encontrada'], 404);
});

// Legacy route - redirige a la nueva
Route::get('/docs', function () {
    return redirect('/api-docs');
});
