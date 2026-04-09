<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use App\Http\Middleware\EnsureUserIsActive;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {

        // Middleware CORS
        $middleware->api(prepend: [
            HandleCors::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'active' => EnsureUserIsActive::class,
        ]);

        // Aplicar middleware de usuario activo a todas las rutas API
        $middleware->api(append: [
            EnsureUserIsActive::class,
        ]);

        $middleware->redirectGuestsTo(function () {
            return null;
        });

    })

    ->withExceptions(function (Exceptions $exceptions): void {
        // Devolver JSON para excepciones de autenticación en solicitudes API
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'No autenticado'], 401);
            }
        });
        
        // Devolver JSON para excepciones de autorización en solicitudes API
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
        });
        
        // Devolver JSON para excepciones de modelo no encontrado en solicitudes API
        $exceptions->render(function (\Illuminate\Database\ModelNotFoundException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Recurso no encontrado'], 404);
            }
        });
    })->create();
