<?php

namespace App\Services;

use App\Models\AcademicSemester;
use App\Models\Activity;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
    public function __construct(private AuditService $audit) {}

    /**
     * Recalcula promedio y semáforo de un curso.
     *
     * Si cambian, lo registra en audit_logs (el SP actualiza la tabla directamente y
     * Eloquent no se entera). $context indica qué lo provocó, por ejemplo ['activity_id' => 7].
     */
    public function refreshCourse(Course $course, array $context = []): void
    {
        $before = $this->courseState($course->id);

        $course->recalculateAverage();

        $after = $this->courseState($course->id);

        if ($before === $after) {
            return;
        }

        $this->audit->log(
            'course',
            $course->id,
            'recalculated',
            $before,
            $context ? $after + ['context' => $context] : $after,
            $this->ownerId($course)
        );
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
            ->each(fn(Course $course) => $this->refreshCourse($course, ['activity_id' => $activity->id]));

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

    /** @return array{current_average?: float, priority_level_id?: int} */
    private function courseState(int $courseId): array
    {
        $row = DB::table('courses')->where('id', $courseId)->first(['current_average', 'priority_level_id']);

        return $row ? [
            'current_average'   => (float) $row->current_average,
            'priority_level_id' => (int) $row->priority_level_id,
        ] : [];
    }

    private function ownerId(Course $course): ?int
    {
        $id = DB::table('academic_semesters')->where('id', $course->semester_id)->value('user_id');

        return $id ? (int) $id : null;
    }
}
