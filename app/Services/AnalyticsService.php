<?php

namespace App\Services;

use App\Models\AcademicSemester;
use App\Models\Activity;
use App\Models\Course;
use App\Models\User;

/**
 * Único punto de entrada para ejecutar los stored procedures de analítica.
 * Nadie del equipo debe llamar recalculateAverage(), syncCalendar() o
 * generateRecommendations() directamente: usen este servicio.
 *
 * El orden importa: primero el semáforo de los cursos, luego el calendario y
 * las recomendaciones, porque ambos leen courses.priority_level_id.
 */
class AnalyticsService
{
    /** Recalcula promedio y semáforo de un curso. */
    public function refreshCourse(Course $course): void
    {
        $course->recalculateAverage();
    }

    /** Sincroniza calendario y regenera recomendaciones del usuario. */
    public function refreshUser(User $user): void
    {
        $user->syncCalendar();
        $user->generateRecommendations();
    }

    /**
     * Refresca todo lo afectado por un cambio en una actividad.
     * Si la actividad cambió de curso, pasar el id del curso anterior
     * para recalcularlo también.
     */
    public function refreshActivity(Activity $activity, ?int $previousCourseId = null): void
    {
        $courseIds = collect([$activity->course_id, $previousCourseId])
            ->filter()
            ->unique();

        Course::withTrashed()
            ->whereIn('id', $courseIds)
            ->get()
            ->each(fn(Course $course) => $this->refreshCourse($course));

        $this->refreshUser(User::findOrFail($activity->user_id));
    }

    /**
     * Recalcula todos los cursos del usuario y luego calendario y recomendaciones.
     * Útil para el dashboard y para el comando programado, porque el semáforo
     * depende de la fecha actual (entregas en las próximas 72 horas).
     */
    public function refreshAll(User $user): void
    {
        $semesterIds = AcademicSemester::where('user_id', $user->id)->pluck('id');

        Course::whereIn('semester_id', $semesterIds)
            ->get()
            ->each(fn(Course $course) => $this->refreshCourse($course));

        $this->refreshUser($user);
    }
}
