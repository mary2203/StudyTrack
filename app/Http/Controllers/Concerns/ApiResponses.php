<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Respuestas JSON con formato estándar para todos los controladores de la API.
 *
 * Éxito: { "data": ..., "message": "opcional" }
 * Error: { "message": "...", "errors": { ... } | null }
 *
 * Para modelos y colecciones usen API Resources (ya envuelven el resultado en "data").
 * Este trait es para respuestas sin recurso: mensajes, listas simples o errores.
 *
 * Uso: en el controlador, `use ApiResponses;`
 */
trait ApiResponses
{
    protected function ok(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        $body = ['data' => $data];

        if ($message !== null) {
            $body['message'] = $message;
        }

        return response()->json($body, $status);
    }

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->ok($data, $message, 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function fail(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }
}
