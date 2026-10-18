<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WebViewsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite' ||
            config('database.connections.sqlite.database') !== ':memory:' ||
            ! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Estas pruebas requieren SQLite en memoria; nunca usan la base real.');
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function test_authentication_pages_render_without_stylesheets(): void
    {
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión');
        $this->get('/register')->assertOk()->assertSee('Crear cuenta');

        $this->assertStringNotContainsString('<style', $this->get('/login')->getContent());
        $this->assertStringNotContainsString('<link', $this->get('/register')->getContent());
    }

    public function test_registration_creates_user_and_starts_web_session(): void
    {
        $response = $this->post('/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'ana@example.com']);
    }

    public function test_registration_validates_password_confirmation_and_unique_email(): void
    {
        User::create([
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'password123',
        ]);

        $this->from('/register')->post('/register', [
            'name' => 'Ana duplicada',
            'email' => 'ana@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ])->assertRedirect('/register')->assertSessionHasErrors(['email', 'password']);
    }

    public function test_login_rejects_invalid_credentials_and_authenticates_valid_user(): void
    {
        User::create([
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'password123',
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'ana@example.com',
            'password' => 'incorrecta',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', [
            'email' => 'ana@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_dashboard_requires_authentication_and_renders_service_data(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));

        $user = User::create([
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'password123',
        ]);
        $this->actingAs($user);

        $dashboardData = [
            'semester' => [
                'name' => 'Semestre A',
                'start_date' => '2026-10-01',
                'end_date' => '2026-12-31',
            ],
            'summary' => [
                'total' => 2,
                'pending' => 1,
                'overdue' => 0,
                'completed' => 1,
                'due_next_7_days' => 1,
                'progress_percent' => 50,
            ],
            'courses' => [],
            'upcoming' => [],
            'recommendations_unread' => 0,
        ];
        $dashboard = $this->mock(DashboardService::class);
        $dashboard->shouldReceive('forUser')->once()->withArgs(fn (User $requestedUser) => $requestedUser->is($user))
            ->andReturn($dashboardData);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Hola, Ana.')
            ->assertSee('Semestre A')
            ->assertSee('Progreso')
            ->assertSee('50%');

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_dashboard_shows_message_when_there_is_no_current_semester(): void
    {
        $user = User::create([
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'password123',
        ]);
        $this->actingAs($user);

        $this->mock(DashboardService::class)
            ->shouldReceive('forUser')
            ->once()
            ->andReturn(null);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('No tienes un semestre actual.');
    }
}
