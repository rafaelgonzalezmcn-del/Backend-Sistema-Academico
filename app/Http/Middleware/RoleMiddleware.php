<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     * Verifica que el usuario tenga el rol requerido.
     * Soporta múltiples roles separados por coma (ej: role:profesor,admin)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Verificar que el usuario esté autenticado
        if (!$user) {
            return response()->json([
                'message' => 'No autenticado'
            ], 401);
        }

        // Verificar que el usuario tenga un rol asignado
        if (!$user->role) {
            return response()->json([
                'message' => 'El usuario no tiene un rol asignado'
            ], 403);
        }

        // Laravel separa los parámetros por coma: "role:profesor,admin" llega
        // como dos argumentos ("profesor", "admin"). Antes se recibía uno solo
        // ($roles = "profesor") y el admin quedaba bloqueado en esas rutas.
        $rolesArray = array_map('trim', $roles);
        
        // Verificar si el rol del usuario está en la lista de roles permitidos
        if (!in_array($user->role->name, $rolesArray)) {
            // No se revela qué rol se necesita ni cuál tiene el usuario
            return response()->json([
                'message' => 'No tienes permisos para acceder a este recurso'
            ], 403);
        }

        return $next($request);
    }
}
