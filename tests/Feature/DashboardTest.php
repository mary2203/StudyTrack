<?php

namespace Tests\Feature;

use App\Models\AcademicSemester;
use App\Models\Activity;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Requiere una base de pruebas con los SP (study_track_test, importada desde
 * study_track.sql) configurada en .env.testing.
 * DatabaseTransactions deshace los datos al terminar; NO usar RefreshDatabase.
 */
class DashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sin_semestre_actual_responde_mensaje(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'No tienes un semestre actual. Crea uno o márcalo como actual.');
    }

    public function test_muestra_curso_con_su_semaforo_y_el_resumen(): void
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

        // Examen ya calificado: 10 de 25 puntos (40 %)
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
        // Tarea pendiente que vence en 2 días
        Activity::forceCreate([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'title' => 'Hoja de trabajo',
            'activity_type' => 'task',
            'is_graded' => true,
            'points_possible' => 15,
            'due_datetime' => now()->addDays(2),
            'status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.semester.name', 'Semestre de prueba')
            ->assertJsonPath('data.courses.0.name', 'Matemática')
            ->assertJsonPath('data.courses.0.current_average', 40)
            ->assertJsonPath('data.courses.0.priority.code', 'CRITICAL_RED')
            ->assertJsonPath('data.courses.0.pending', 1)
            ->assertJsonPath('data.courses.0.next_due.title', 'Hoja de trabajo')
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.completed', 1)
            ->assertJsonPath('data.summary.progress_percent', 50);
    }

    public function test_no_incluye_datos_de_otro_usuario(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $semester = AcademicSemester::forceCreate([
            'user_id' => $owner->id,
            'name' => 'Ajeno',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'is_current' => true,
        ]);
        Course::forceCreate(['semester_id' => $semester->id, 'name' => 'Curso ajeno']);

        Sanctum::actingAs($other);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_sin_token_responde_401(): void
    {
        $this->get('/api/dashboard')->assertUnauthorized();
    }
}
