<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formato único de respuestas de la API ("contrato de respuesta").
 *
 * Éxito:
 *   {
 *     "data":    <objeto | lista>,          // lo que el cliente pidió
 *     "message": "texto opcional",          // solo en acciones (crear, editar...)
 *     "meta":    { ... }                    // opcional: paginación o contexto
 *   }
 *
 * Error:
 *   {
 *     "message": "Descripción del error",
 *     "errors":  { "campo": ["detalle"] },  // opcional: errores de validación
 *     "data":    { ... }                    // opcional: contexto del error
 *   }
 *
 * Todos los controladores heredan este trait desde Controller.
 */
trait ApiResponse
{
    /**
     * Respuesta exitosa estándar.
     */
    protected function success(mixed $data = null, ?string $message = null, array $meta = [], int $status = 200): JsonResponse
    {
        if ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        }

        $body = ['data' => $data];

        if ($message !== null) {
            $body['message'] = $message;
        }
        if (!empty($meta)) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }

    /**
     * Recurso creado (HTTP 201).
     */
    protected function created(mixed $data, string $message, array $meta = []): JsonResponse
    {
        return $this->success($data, $message, $meta, 201);
    }

    /**
     * Respuesta de error estándar.
     */
    protected function error(string $message, int $status = 400, array $errors = [], mixed $data = null): JsonResponse
    {
        $body = ['message' => $message];

        if (!empty($errors)) {
            $body['errors'] = $errors;
        }
        if ($data !== null) {
            $body['data'] = $data;
        }

        return response()->json($body, $status);
    }
}
