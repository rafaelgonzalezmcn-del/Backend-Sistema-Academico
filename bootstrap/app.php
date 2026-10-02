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
        
        // 404: Laravel convierte ModelNotFoundException (ID inexistente en la ruta)
        // en NotFoundHttpException ANTES de llegar aquí, por eso se captura esta.
        // Así nunca se devuelve el stack trace ni rutas internas del servidor.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                $message = $e->getPrevious() instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                    ? 'Recurso no encontrado'
                    : 'Ruta no encontrada';
                return response()->json(['message' => $message], 404);
            }
        });

        // 405: método HTTP no permitido en la ruta
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Método no permitido'], 405);
            }
        });

        // 429: límite de solicitudes (throttle)
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json(
                    ['message' => 'Demasiadas solicitudes. Intenta nuevamente en un momento.'],
                    429,
                    $e->getHeaders()
                );
            }
        });
    })->create();
