<?php

namespace Tests\Feature;

use App\Models\AcademicSemester;
use App\Models\Activity;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Requiere la base de pruebas con los SP (study_track_test). Usa DatabaseTransactions;
 * NO usar RefreshDatabase.
 */
class ScheduledCommandsTest extends TestCase
{
    use DatabaseTransactions;

    private function userWithCourse(): array
    {
        $user = User::factory()->create();
        $semester = AcademicSemester::forceCreate([
            'user_id' => $user->id,
            'name' => 'Semestre de prueba',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'is_current' => true,
        ]);
        $course = Course::forceCreate(['semester_id' => $semester->id, 'name' => 'Matemática']);

        return [$user, $course];
    }

    private function activity(User $user, Course $course, string $title, string $status, $due): Activity
    {
        return Activity::forceCreate([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'title' => $title,
            'activity_type' => 'task',
            'is_graded' => false,
            'due_datetime' => $due,
            'status' => $status,
        ]);
    }

    public function test_marca_solo_las_pendientes_o_en_progreso_ya_vencidas(): void
    {
        [$user, $course] = $this->userWithCourse();

        $pasadaPendiente   = $this->activity($user, $course, 'Pasada pendiente', 'pending', now()->subDay());
        $pasadaEnProgreso  = $this->activity($user, $course, 'Pasada en progreso', 'in_progress', now()->subDay());
        $futura            = $this->activity($user, $course, 'Futura', 'pending', now()->addDays(2));
        $pasadaCompletada  = $this->activity($user, $course, 'Pasada completada', 'completed', now()->subDay());
        $pasadaBorrada     = $this->activity($user, $course, 'Pasada borrada', 'pending', now()->subDay());
        $pasadaBorrada->delete();

        $this->artisan('activities:mark-overdue')->assertExitCode(0);

        $status = fn(Activity $a) => DB::table('activities')->where('id', $a->id)->value('status');

        $this->assertSame('overdue', $status($pasadaPendiente));
        $this->assertSame('overdue', $status($pasadaEnProgreso));
        $this->assertSame('pending', $status($futura));
        $this->assertSame('completed', $status($pasadaCompletada));
        $this->assertSame('pending', $status($pasadaBorrada));
    }

    public function test_sin_actividades_vencidas_termina_bien(): void
    {
        [$user, $course] = $this->userWithCourse();
        $this->activity($user, $course, 'Futura', 'pending', now()->addDays(2));

        $this->artisan('activities:mark-overdue')
            ->expectsOutput('No hay actividades vencidas por marcar.')
            ->assertExitCode(0);
    }

    public function test_refresh_recalcula_el_semaforo_de_los_cursos(): void
    {
        [$user, $course] = $this->userWithCourse();

        Activity::forceCreate([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'title' => 'Parcial',
            'activity_type' => 'exam',
            'is_graded' => true,
            'points_possible' => 25,
            'points_obtained' => 10,
            'due_datetime' => now()->subDays(3),
            'status' => 'completed',
        ]);
        $this->activity($user, $course, 'Entrega próxima', 'pending', now()->addDays(2));

        // Se deja el curso con datos desactualizados a propósito
        DB::table('courses')->where('id', $course->id)
            ->update(['priority_level_id' => 3, 'current_average' => 0]);

        $this->artisan('analytics:refresh')->assertExitCode(0);

        $row = DB::table('courses')->where('id', $course->id)->first();
        $this->assertEquals(40, (float) $row->current_average);
        $this->assertSame(1, (int) $row->priority_level_id);
    }
}
