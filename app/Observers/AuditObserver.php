<?php

namespace App\Observers;

use App\Models\Course;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registra en audit_logs lo que cambia en Activity y Course.
 * Se registra con Activity::observe() y Course::observe() (ver AppServiceProvider);
 * convive con ActivityObserver, que sigue encargándose de la analítica.
 *
 * Ojo: los cambios de current_average y priority_level_id los hace el stored procedure,
 * no Eloquent, y se registran en AnalyticsService::refreshCourse().
 */
class AuditObserver
{
    public function __construct(private AuditService $audit) {}

    public function created(Model $model): void
    {
        $this->audit->log(
            $this->entity($model),
            $model->getKey(),
            'created',
            null,
            $this->snapshot($model),
            $this->ownerId($model)
        );
    }

    public function updated(Model $model): void
    {
        $changes = Arr::except($model->getChanges(), AuditService::IGNORED);

        // Restaurar un registro cambia solo deleted_at: ya lo cubre restored()
        if ($changes === []) {
            return;
        }

        $this->audit->log(
            $this->entity($model),
            $model->getKey(),
            'updated',
            Arr::only($model->getOriginal(), array_keys($changes)),
            $changes,
            $this->ownerId($model)
        );
    }

    public function deleted(Model $model): void
    {
        $forced = method_exists($model, 'isForceDeleting') && $model->isForceDeleting();

        $this->audit->log(
            $this->entity($model),
            $model->getKey(),
            $forced ? 'force_deleted' : 'deleted',
            $this->snapshot($model),
            null,
            $this->ownerId($model)
        );
    }

    public function restored(Model $model): void
    {
        $this->audit->log(
            $this->entity($model),
            $model->getKey(),
            'restored',
            null,
            $this->snapshot($model),
            $this->ownerId($model)
        );
    }

    private function entity(Model $model): string
    {
        return Str::snake(class_basename($model));
    }

    private function snapshot(Model $model): array
    {
        return Arr::except($model->getAttributes(), AuditService::IGNORED);
    }

    private function ownerId(Model $model): ?int
    {
        if ($model->getAttribute('user_id')) {
            return (int) $model->getAttribute('user_id');
        }

        if ($model instanceof Course) {
            $id = DB::table('academic_semesters')->where('id', $model->semester_id)->value('user_id');

            return $id ? (int) $id : null;
        }

        return Auth::id() ? (int) Auth::id() : null;
    }
}
