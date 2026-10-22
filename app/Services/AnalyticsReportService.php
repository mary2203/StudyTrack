<?php

namespace App\Services;

use App\Models\AcademicSemester;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Collection;

class AnalyticsReportService
{
    private const LEVELS = ['descriptive', 'diagnostic', 'predictive', 'prescriptive'];

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user, string $level): array
    {
        abort_unless(in_array($level, self::LEVELS, true), 404);

        $semester = AcademicSemester::query()
            ->where('user_id', $user->id)
            ->where('is_current', true)
            ->first();

        $courses = $semester
            ? Course::query()->where('semester_id', $semester->id)->orderBy('name')->get()
            : collect();

        return [
            'level' => $level,
            'semester' => $semester ? [
                'id' => $semester->id,
                'name' => $semester->name,
                'start_date' => $semester->start_date->toDateString(),
                'end_date' => $semester->end_date->toDateString(),
            ] : null,
            'data' => match ($level) {
                'descriptive' => $this->descriptive($user, $courses),
                'diagnostic' => $this->diagnostic($user),
                'predictive' => $this->predictive($courses, $user->id),
                'prescriptive' => $this->prescriptive($courses, $user->id),
            },
        ];
    }

    /** @param Collection<int, Course> $courses */
    private function descriptive(User $user, Collection $courses): array
    {
        $courseIds = $courses->pluck('id');
        $activities = Activity::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($courseIds) {
                $query->whereNull('course_id')->orWhereIn('course_id', $courseIds);
            })
            ->get();

        return [
            'summary' => [
                'activities_total' => $activities->count(),
                'pending' => $activities->whereIn('status', ['pending', 'in_progress'])->count(),
                'overdue' => $activities->where('status', 'overdue')->count(),
                'completed' => $activities->where('status', 'completed')->count(),
                'graded' => $activities->where('is_graded', true)->whereNotNull('points_obtained')->count(),
            ],
            'courses' => $courses->map(function (Course $course) use ($activities) {
                $courseActivities = $activities->where('course_id', $course->id);

                return [
                    'id' => $course->id,
                    'name' => $course->name,
                    'current_average' => (float) $course->current_average,
                    'target_grade' => (float) $course->target_grade,
                    'activities_total' => $courseActivities->count(),
                    'pending' => $courseActivities->whereIn('status', ['pending', 'in_progress'])->count(),
                    'overdue' => $courseActivities->where('status', 'overdue')->count(),
                    'completed' => $courseActivities->where('status', 'completed')->count(),
                ];
            })->values()->all(),
        ];
    }

    private function diagnostic(User $user): array
    {
        $logs = AuditLog::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit(50)
            ->get();

        return [
            'events' => $logs->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'entity' => $log->entity_name,
                'entity_id' => $log->entity_id,
                'action' => $log->action,
                'old_data' => $log->old_data,
                'new_data' => $log->new_data,
                'created_at' => $log->created_at,
            ])->all(),
            'events_count' => $logs->count(),
            'limit' => 50,
        ];
    }

    /** @param Collection<int, Course> $courses */
    private function predictive(Collection $courses, int $userId): array
    {
        $components = config('studytrack.grading_components');
        $minimumPassingGrade = (int) config('studytrack.minimum_passing_grade');

        return [
            'method' => 'Estimación de aprobación según los puntos de las actividades registradas; no es una probabilidad estadística ni una nota oficial ponderada.',
            'overdue_policy' => 'Las actividades vencidas sin nota se consideran con cero puntos. Las actividades entregadas pero aún sin calificar siguen contando como puntos posibles.',
            'grading_rule' => [
                'components' => $components,
                'minimum_passing_grade' => $minimumPassingGrade,
            ],
            'courses' => $courses->map(fn (Course $course) => $this->courseRisk($course, $userId))->values()->all(),
        ];
    }

    /** @param Collection<int, Course> $courses */
    private function prescriptive(Collection $courses, int $userId): array
    {
        $risks = $courses->map(fn (Course $course) => $this->courseRisk($course, $userId));
        $actions = $risks->map(function (array $risk) {
            $actions = match ($risk['risk_level']) {
                'high' => [
                    'Contacta al docente para revisar opciones de recuperación o evaluación adicional.',
                    'Prioriza cualquier actividad calificable próxima y solicita un plan de recuperación.',
                ],
                'medium' => [
                    'Organiza un plan de estudio para alcanzar el promedio requerido en las actividades restantes.',
                    'Revisa los temas con menor desempeño antes de la siguiente evaluación.',
                ],
                'low' => [
                    'Mantén tu ritmo de estudio y revisa regularmente tus próximas actividades.',
                ],
                default => [
                    'Registra al menos una calificación para obtener una estimación de riesgo más informativa.',
                ],
            };

            return [
                'course_id' => $risk['course_id'],
                'course_name' => $risk['course_name'],
                'risk_level' => $risk['risk_level'],
                'actions' => $actions,
            ];
        })->values()->all();

        return ['actions' => $actions];
    }

    /** @return array<string, mixed> */
    private function courseRisk(Course $course, int $userId): array
    {
        $activities = $course->activities()
            ->where('user_id', $userId)
            ->where('is_graded', true)
            ->where('points_possible', '>', 0)
            ->get(['id', 'points_possible', 'points_obtained', 'due_datetime', 'status']);

        $now = now();
        $totalPossible = 0.0;
        $earned = 0.0;
        $scoredPossible = 0.0;
        $remaining = 0.0;
        $overdueUngraded = 0.0;

        foreach ($activities as $activity) {
            $possible = (float) $activity->points_possible;
            $totalPossible += $possible;

            if ($activity->points_obtained !== null) {
                $earned += (float) $activity->points_obtained;
                $scoredPossible += $possible;

                continue;
            }

            $isOverdue = $activity->status === 'overdue'
                || ($activity->status !== 'completed'
                    && $activity->due_datetime !== null
                    && $activity->due_datetime->lt($now));

            if ($isOverdue) {
                $overdueUngraded += $possible;
                continue;
            }

            $remaining += $possible;
        }

        $passingGrade = (float) config('studytrack.minimum_passing_grade');
        $average = $scoredPossible > 0 ? ($earned / $scoredPossible) * 100 : null;
        $requiredPoints = ($passingGrade / 100) * $totalPossible - $earned;
        $requiredAverage = $remaining > 0
            ? ($requiredPoints / $remaining) * 100
            : null;
        $maximumFinal = $totalPossible > 0 ? (($earned + $remaining) / $totalPossible) * 100 : null;

        if ($totalPossible <= 0) {
            $risk = 'insufficient_data';
        } elseif ($requiredPoints > $remaining + 0.00001) {
            $risk = 'high';
        } elseif ($scoredPossible <= 0) {
            $risk = 'insufficient_data';
        } elseif ($requiredPoints <= 0 || $requiredAverage <= $average) {
            $risk = 'low';
        } else {
            $risk = 'medium';
        }

        return [
            'course_id' => $course->id,
            'course_name' => $course->name,
            'passing_grade' => round($passingGrade, 2),
            'target_grade' => $course->target_grade === null ? null : round((float) $course->target_grade, 2),
            'current_average' => $average === null ? null : round($average, 2),
            'required_average_on_remaining' => $requiredAverage === null
                ? null
                : round(max(0, $requiredAverage), 2),
            'maximum_final_grade' => $maximumFinal === null ? null : round($maximumFinal, 2),
            'pass_is_reachable' => $maximumFinal !== null && $maximumFinal + 0.00001 >= $passingGrade,
            'graded_activities' => $activities->whereNotNull('points_obtained')->count(),
            'remaining_points' => round($remaining, 2),
            'overdue_ungraded_points_counted_as_zero' => round($overdueUngraded, 2),
            'risk_level' => $risk,
        ];
    }
}
