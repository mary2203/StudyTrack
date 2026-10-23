<?php

namespace Tests\Feature;

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
}
