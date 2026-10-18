<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\CalendarEvent;
use App\Models\User;

class WebDashboardService
{
    private const ANALYTICS_LEVELS = ['descriptive', 'diagnostic', 'predictive', 'prescriptive'];

    public function __construct(
        private DashboardService $dashboard,
        private AnalyticsReportService $analytics,
        private RecommendationService $recommendations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user, ?string $search = null, ?string $status = null): array
    {
        $dashboard = $this->dashboard->forUser($user);
        $courseIds = collect($dashboard['courses'] ?? [])->pluck('id');

        $activities = Activity::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($courseIds) {
                $query->whereNull('course_id')->orWhereIn('course_id', $courseIds);
            })
            ->with('course:id,name')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhereHas('course', fn ($courseQuery) => $courseQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('due_datetime')
            ->get();

        $weekStart = now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();
        $calendarEvents = CalendarEvent::query()
            ->where('user_id', $user->id)
            ->whereBetween('event_datetime', [$weekStart, $weekEnd])
            ->with('course:id,name')
            ->orderBy('event_datetime')
            ->get();

        $analytics = collect(self::ANALYTICS_LEVELS)
            ->mapWithKeys(fn (string $level) => [$level => $this->analytics->forUser($user, $level)['data']])
            ->all();

        return [
            'dashboard' => $dashboard,
            'activities' => $activities,
            'calendar' => [
                'week_start' => $weekStart,
                'week_end' => $weekEnd,
                'events' => $calendarEvents,
            ],
            'recommendations' => $this->recommendations->activeQuery($user)
                ->with('course:id,name')
                ->orderByDesc('created_at')
                ->get(),
            'analytics' => $analytics,
            'activity_search' => $search,
            'activity_status' => $status,
        ];
    }
}
