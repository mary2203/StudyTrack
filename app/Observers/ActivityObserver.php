<?php

namespace App\Observers;

use App\Models\Activity;
use App\Services\AnalyticsService;

class ActivityObserver
{
    public function created(Activity $activity): void
    {
        app(AnalyticsService::class)->refreshActivity($activity);
    }

    public function updated(Activity $activity): void
    {
        // Al restaurar un SoftDelete, 'updated' también se dispara. 'restored'
        // hará el recálculo una sola vez, sin repetir el procedimiento.
        if ($activity->wasChanged('deleted_at') && ! $activity->wasChanged([
            'course_id', 'title', 'description', 'activity_type', 'is_graded',
            'points_possible', 'points_obtained', 'due_datetime', 'estimated_minutes',
            'status', 'priority_level_id', 'completed_at',
        ])) {
            return;
        }

        $previousCourseId = $activity->wasChanged('course_id')
            ? $activity->getOriginal('course_id')
            : null;

        app(AnalyticsService::class)->refreshActivity($activity, $previousCourseId === null ? null : (int) $previousCourseId);
    }

    public function deleted(Activity $activity): void
    {
        app(AnalyticsService::class)->refreshActivity($activity);
    }

    public function restored(Activity $activity): void
    {
        app(AnalyticsService::class)->refreshActivity($activity);
    }
}
