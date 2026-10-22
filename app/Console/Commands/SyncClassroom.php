<?php

namespace App\Console\Commands;

use App\Models\ClassroomConnection;
use App\Services\GoogleClassroomService;
use Illuminate\Console\Command;
use Throwable;

class SyncClassroom extends Command
{
    protected $signature = 'classroom:sync';

    protected $description = 'Sincroniza cursos, tareas y calificaciones de Google Classroom';

    public function handle(GoogleClassroomService $classroom): int
    {
        $failed = false;

        ClassroomConnection::query()->with('user')->chunkById(100, function ($connections) use ($classroom, &$failed) {
            foreach ($connections as $connection) {
                try {
                    $result = $classroom->sync($connection->user);
                    $this->info(
                        "Usuario {$connection->user_id}: {$result['courses_found']} cursos encontrados, "
                        . "{$result['activities_synced']} actividades sincronizadas, "
                        . "{$result['pending_courses']} pendientes de asignar."
                    );
                } catch (Throwable $exception) {
                    $failed = true;
                    report($exception);
                    $this->error("Falló la sincronización para el usuario {$connection->user_id}: {$exception->getMessage()}");
                }
            }
        });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
