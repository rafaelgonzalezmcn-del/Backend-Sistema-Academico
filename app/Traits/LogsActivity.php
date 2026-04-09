<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

/**
 * Trait para registrar actividades de usuario en el sistema
 * 
 * Proporciona métodos para registrar eventos de manera estandarizada
 * en el modelo ActivityLog. Diseñado para usarse en Services.
 * 
 * ## Uso básico
 * 
 * 1. Usar el trait en la clase:
 *    ```php
 *    class MiServicio
 *    {
 *        use LogsActivity;
 *        // ...
 *    }
 *    ```
 * 
 * 2. Registrar actividades usando logEvent() para el formato estándar:
 *    ```php
 *    // Para operaciones create/update/delete
 *    $this->logActivity($this->logEvent('recurso', 'created'), $modelo);
 *    $this->logActivity($this->logEvent('recurso', 'updated'), $modelo, $changes);
 *    $this->logActivity($this->logEvent('recurso', 'deleted'), $modelo);
 *    ```
 * 
 * ## Formato de eventos
 * 
 * Formato: `recurso.acción`
 * - recurso: singular, minúsculas (ej: materia, tarea, entrega)
 * - acción: pasado simple en inglés (created, updated, deleted, activated, graded, uploaded)
 * 
 * ### Acciones comunes:
 * | Recurso    | Acciones válidas                              |
 * |------------|----------------------------------------------|
 * | materia    | created, updated, deleted                   |
 * | modulo     | created, updated, deleted                   |
 * | material   | uploaded, deleted                            |
 * | tarea      | created, updated, deleted                   |
 * | entrega    | created, updated, graded, deleted            |
 * | section    | created, updated, deleted                    |
 * | grade      | created, updated, deleted                    |
 * | school_year| created, updated, activated                 |
 * | class_schedule | created, updated, deleted              |
 * 
 * ## Notas importantes
 * 
 * - Los logs solo se registran cuando la operación es exitosa (después de transacciones)
 * - No se registran eventos si no hay cambios reales en operaciones update
 * - Usar $force=true para forzar registro aunque no haya cambios
 * - El método getChanges() detecta automáticamente qué campos cambiaron
 */
trait LogsActivity
{
    /**
     * Guardar registro de actividad
     * 
     * Método principal para registrar actividades. Automáticamente captura:
     * - Usuario autenticado (de la request)
     * - IP address
     * - User agent
     * 
     * @param string $description Descripción de la acción (formato: recurso.acción)
     * @param mixed $subject Modelo relacionado (opcional)
     * @param array|null $changes Cambios realizados (opcional). Formato: ['campo' => ['before' => valor, 'after' => valor]]
     * @param bool|int $forceOrUserId Forzar registro (true/false) o user_id explícito (int)
     * @return void
     * 
     * @example
     * $this->logActivity('Usuario inició sesión', $user, null, $user->id);  // Con user_id explícito
     * $this->logActivity('tarea.created', $tarea, null, true);              // Con force=true
     */
    protected function logActivity($description, $subject = null, $changes = null, $forceOrUserId = false): void
    {
        try {
            // Si hay cambios pero están vacíos y no se fuerza, no registrar
            // Esto evita logs innecesarios cuando no hay cambios reales
            if ($forceOrUserId !== true && $changes !== null && empty($changes)) {
                return;
            }

            $request = request();
            $requestUser = $request->user();
            
            // Determinar user_id:
            // - Si $forceOrUserId es un int, usarlo directamente (para login/logout)
            // - Si $forceOrUserId es true, forzar registro aunque no haya usuario
            // - Si no, usar el usuario de la request
            if (is_int($forceOrUserId)) {
                $userId = $forceOrUserId;
            } elseif ($requestUser) {
                $userId = $requestUser->id;
            } elseif ($forceOrUserId === true) {
                // Forzar sin usuario - permitido para login/logout
                $userId = null;
            } else {
                $userId = null;
            }
            
            ActivityLog::create([
                'description' => $description,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject ? $subject->id : null,
                'user_id' => $userId,
                'changes' => $changes,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            // No fallar la operación principal si el logging falla
            Log::error('Error logging activity: ' . $e->getMessage());
        }
    }

    /**
     * Generar descripción de evento en formato estándar
     * 
     * Método helper para crear descripciones consistentes.
     * Formato: recurso.acción (ej: tarea.created, materia.updated)
     * 
     * @param string $resource Nombre del recurso (singular, minúsculas)
     * @param string $action Acción realizada (created, updated, deleted, activated, graded, uploaded, etc.)
     * @return string Descripción formateada (ej: "tarea.created")
     * 
     * @example
     * $this->logEvent('materia', 'created');   // "materia.created"
     * $this->logEvent('tarea', 'updated');      // "tarea.updated"
     * $this->logEvent('entrega', 'graded');    // "entrega.graded"
     */
    protected function logEvent(string $resource, string $action): string
    {
        return "{$resource}.{$action}";
    }

    /**
     * Obtener diferencias entre dos arrays
     * 
     * Compara datos anteriores con nuevos y retorna solo los campos que cambiaron.
     * Útil para registrar cambios en operaciones update.
     * 
     * @param array $old Datos anteriores (del modelo antes de actualizar)
     * @param array $new Datos nuevos (los que se aplicarán)
     * @return array Array asociativo con los cambios. Formato:
     *               ['campo' => ['before' => valor_old, 'after' => valor_new]]
     * 
     * @example
     * $oldData = ['name' => 'Matemáticas', 'active' => true];
     * $newData = ['name' => 'Matemáticas Avanzadas', 'active' => true];
     * $changes = $this->getChanges($oldData, $newData);
     * // Resultado: ['name' => ['before' => 'Matemáticas', 'after' => 'Matemáticas Avanzadas']]
     */
    protected function getChanges($old, $new): array
    {
        $changes = [];

        foreach ($new as $key => $value) {
            // Comparar valores (incluye cambio de null a valor o viceversa)
            if (!array_key_exists($key, $old) || $old[$key] !== $value) {
                $changes[$key] = [
                    'before' => $old[$key] ?? null,
                    'after' => $value
                ];
            }
        }

        return $changes;
    }
}
