<?php

namespace Tests\Feature;

use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisteredUserDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_newly_registered_user_can_load_dashboard_without_a_semester(): void
    {
        $this->post('/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Aún no tienes un semestre actual');
    }

    public function test_registered_user_can_create_a_semester_from_the_dashboard_session(): void
    {
        $this->post('/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->postJson(route('dashboard-data.semesters.store'), [
            'name' => 'Segundo semestre 2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-11-30',
            'is_current' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Segundo semestre 2026');

        $this->assertDatabaseHas('academic_semesters', [
            'name' => 'Segundo semestre 2026',
            'user_id' => auth()->id(),
        ]);
    }

    public function test_registered_user_can_create_a_course_and_activity_from_the_dashboard_session(): void
    {
        $this->post('/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $semesterResponse = $this->postJson(route('dashboard-data.semesters.store'), [
            'name' => 'Segundo semestre 2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-11-30',
            'is_current' => true,
        ])->assertCreated();
        $semesterId = $semesterResponse->json('data.id');

        $courseResponse = $this->postJson(route('dashboard-data.courses.store'), [
            'semester_id' => $semesterId,
            'name' => 'Analítica de negocios',
            'code' => 'AN-101',
            'color_hex' => '#526531',
            'target_grade' => 85,
        ])->assertCreated();
        $courseId = $courseResponse->json('data.id');

        $this->mock(AnalyticsService::class)
            ->shouldReceive('refreshActivity')
            ->once();

        $this->postJson(route('dashboard-data.activities.store'), [
            'course_id' => $courseId,
            'title' => 'Proyecto final',
            'activity_type' => 'project',
            'is_graded' => true,
            'points_possible' => 100,
            'due_datetime' => '2026-11-20 23:59:00',
            'status' => 'pending',
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Proyecto final');

        $this->assertDatabaseHas('courses', [
            'id' => $courseId,
            'semester_id' => $semesterId,
        ]);
        $this->assertDatabaseHas('activities', [
            'title' => 'Proyecto final',
            'course_id' => $courseId,
            'user_id' => auth()->id(),
        ]);
    }
}
