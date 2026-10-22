<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Escribe en audit_logs (bitácora de la analítica diagnóstica: por qué bajó un
 * promedio, por qué venció una tarea).
 *
 * Se usa el query builder y no el modelo AuditLog porque la tabla no tiene
 * updated_at y así no importa cómo esté configurado $timestamps en el modelo.
 */
class AuditService
{
    /** Campos que nunca se guardan en la bitácora. */
    public const IGNORED = ['updated_at', 'deleted_at', 'password', 'remember_token'];

    public function log(
        string $entity,
        int $entityId,
        string $action,
        ?array $old,
        ?array $new,
        ?int $userId = null
    ): void {
        $this->insert([$this->row($entity, $entityId, $action, $old, $new, $userId)]);
    }

    /** @param array<int, array<string, mixed>> $rows filas creadas con row() */
    public function logMany(array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            $this->insert($chunk);
        }
    }

    /** @return array<string, mixed> */
    public function row(
        string $entity,
        int $entityId,
        string $action,
        ?array $old,
        ?array $new,
        ?int $userId = null
    ): array {
        return [
            'user_id'     => $userId,
            'entity_name' => $entity,
            'entity_id'   => $entityId,
            'action'      => $action,
            'old_data'    => $this->encode($old),
            'new_data'    => $this->encode($new),
        ];
    }

    private function encode(?array $data): ?string
    {
        return $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;
    }

    private function insert(array $rows): void
    {
        try {
            DB::table('audit_logs')->insert($rows);
        } catch (\Throwable $e) {
            // Un fallo de la bitácora no debe impedir la acción del estudiante
            report($e);
        }
    }
}
