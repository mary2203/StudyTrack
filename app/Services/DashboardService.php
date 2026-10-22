<?php

namespace App\Services;

use App\Models\AcademicSemester;
use App\Models\Activity;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Arma los datos del dashboard del estudiante.
 *
 * Los cálculos (promedio, semáforo, calendario, recomendaciones) los hacen los
 * stored procedures a través de AnalyticsService; aquí solo se leen y se agrupan.
 */
class DashboardService
{
    public function __construct(
        private AnalyticsService $analytics,
        private RecommendationService $recommendations,
    ) {}

    /**
     * @return array<string, mixed>|null  null si el usuario no tiene semestre actual
     */
    public function forUser(User $user): ?array
    {
        $semester = AcademicSemester::where('user_id', $user->id)
            ->where('is_current', true)
            ->first();

        if (! $semester) {
            return null;
        }

        // Relación «include» del caso de uso: primero se actualiza la analítica
        $this->analytics->refreshAll($user);

        // Más urgentes primero (1 = rojo, 2 = amarillo, 3 = verde), luego por nombre
        $courses = Course::with('priorityRule')
            ->where('semester_id', $semester->id)
            ->orderBy('priority_level_id')
            ->orderBy('name')
            ->get();

        $courseIds = $courses->pluck('id');

        // Actividades del semestre actual + las que no pertenecen a ningún curso (trámites, reuniones)
        $scope = fn($query) => $query
            ->where('user_id', $user->id)
            ->where(function ($q) use ($courseIds) {
                $q->whereNull('course_id')->orWhereIn('course_id', $courseIds);
            });

        $statsByCourse = $scope(Activity::query())
            ->whereNotNull('course_id')
            ->selectRaw(
                "course_id,
                 COALESCE(SUM(status IN ('pending','in_progress')), 0) AS pending,
                 COALESCE(SUM(status = 'overdue'), 0) AS overdue,
                 COALESCE(SUM(status = 'completed'), 0) AS completed"
            )
            ->groupBy('course_id')
            ->get()
            ->keyBy('course_id');

        $nextByCourse = $scope(Activity::query())
            ->whereNotNull('course_id')
            ->whereIn('status', ['pending', 'in_progress'])
            ->where('due_datetime', '>=', now())
            ->orderBy('due_datetime')
            ->get()
            ->groupBy('course_id')
            ->map->first();

        $summary = $scope(Activity::query())
            ->selectRaw(
                "COUNT(*) AS total,
                 COALESCE(SUM(status IN ('pending','in_progress')), 0) AS pending,
                 COALESCE(SUM(status = 'overdue'), 0) AS overdue,
                 COALESCE(SUM(status = 'completed'), 0) AS completed"
            )
            ->first();

        $dueNextWeek = $scope(Activity::query())
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereBetween('due_datetime', [now(), now()->addDays(7)])
            ->count();

        $upcoming = $scope(Activity::query())
            ->with('course:id,name,color_hex')
            ->whereIn('status', ['pending', 'in_progress'])
            ->where('due_datetime', '>=', now())
            ->orderBy('due_datetime')
            ->limit(5)
            ->get();

        $total = (int) $summary->total;
        $completed = (int) $summary->completed;

        return [
            'semester' => [
                'id'         => $semester->id,
                'name'       => $semester->name,
                'start_date' => Carbon::parse($semester->start_date)->toDateString(),
                'end_date'   => Carbon::parse($semester->end_date)->toDateString(),
            ],
            'summary' => [
                'total'            => $total,
                'pending'          => (int) $summary->pending,
                'overdue'          => (int) $summary->overdue,
                'completed'        => $completed,
                'due_next_7_days'  => $dueNextWeek,
                'progress_percent' => $total > 0 ? (int) round($completed / $total * 100) : 0,
            ],
            'courses' => $courses->map(function (Course $course) use ($statsByCourse, $nextByCourse) {
                $stats = $statsByCourse->get($course->id);
                $next = $nextByCourse->get($course->id);
                $rule = $course->priorityRule;

                return [
                    'id'              => $course->id,
                    'name'            => $course->name,
                    'code'            => $course->code,
                    'is_classroom_synced' => $course->classroom_course_id !== null,
                    'color_hex'       => $course->color_hex,
                    'current_average' => (float) $course->current_average,
                    'target_grade'    => (float) $course->target_grade,
                    'priority'        => $rule ? [
                        'code'        => $rule->code,
                        'name'        => $rule->name,
                        'color_hex'   => $rule->color_hex,
                        'badge_label' => $rule->badge_label,
                    ] : null,
                    'pending'   => (int) ($stats->pending ?? 0),
                    'overdue'   => (int) ($stats->overdue ?? 0),
                    'completed' => (int) ($stats->completed ?? 0),
                    'next_due'  => $next ? [
                        'id'           => $next->id,
                        'title'        => $next->title,
                        'due_datetime' => $next->due_datetime->toIso8601String(),
                    ] : null,
                ];
            })->values()->all(),
            'upcoming' => $upcoming->map(fn(Activity $a) => [
                'id'            => $a->id,
                'title'         => $a->title,
                'activity_type' => $a->activity_type,
                'due_datetime'  => $a->due_datetime->toIso8601String(),
                'course'        => $a->course ? [
                    'id'        => $a->course->id,
                    'name'      => $a->course->name,
                    'color_hex' => $a->course->color_hex,
                ] : null,
            ])->values()->all(),
            'recommendations_unread' => $this->recommendations->activeQuery($user)->count(),
        ];
    }
}
