<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WebDashboardService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WebViewsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

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
        Schema::create('academic_semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });
        Schema::create('classroom_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('google_email');
            $table->text('refresh_token');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
        Schema::create('classroom_course_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('google_course_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('section')->nullable();
            $table->string('course_state');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_authentication_pages_render_without_stylesheets(): void
    {
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión');
        $this->get('/register')->assertOk()->assertSee('Crear cuenta');

        $this->assertStringNotContainsString('<style', $this->get('/login')->getContent());
        $this->assertStringNotContainsString('<style', $this->get('/register')->getContent());
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

    private function pageData(?array $dashboard): array
    {
        return [
            'dashboard' => $dashboard,
            'activities' => new Collection,
            'calendar' => [
                'week_start' => Carbon::parse('2026-10-12'),
                'week_end' => Carbon::parse('2026-10-18'),
                'events' => new Collection,
            ],
            'recommendations' => new Collection,
            'analytics' => [
                'descriptive' => ['summary' => [
                    'activities_total' => 0,
                    'pending' => 0,
                    'overdue' => 0,
                    'completed' => 0,
                    'graded' => 0,
                ], 'courses' => []],
                'diagnostic' => ['events' => []],
                'predictive' => [
                    'method' => 'Método de prueba.',
                    'overdue_policy' => 'Política de prueba.',
                    'grading_rule' => [
                        'components' => ['zona' => 30, 'parcial_1' => 20, 'parcial_2' => 20, 'parcial_final' => 30],
                        'minimum_passing_grade' => 71,
                    ],
                    'courses' => [],
                ],
                'prescriptive' => ['actions' => []],
            ],
            'activity_search' => null,
            'activity_status' => null,
        ];
    }

    public function test_dashboard_requires_authentication_and_renders_prototype_sections(): void
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
        $this->mock(WebDashboardService::class)
            ->shouldReceive('forUser')
            ->once()
            ->with($user, null, null)
            ->andReturn($this->pageData($dashboardData));

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Ana')
            ->assertSee('Semestre A')
            ->assertSee('Google Classroom')
            ->assertSee('Conectar cuenta')
            ->assertSee('Tu progreso académico en un solo lugar')
            ->assertSee('50%')
            ->assertSee('Mis cursos')
            ->assertSee('Mis actividades')
            ->assertSee('Calendario académico')
            ->assertSee('Descriptiva · ¿Qué pasó?')
            ->assertSee('Diagnóstica · ¿Qué cambió?')
            ->assertSee('Predictiva · ¿Qué podría pasar?')
            ->assertSee('Prescriptiva · ¿Qué conviene hacer?')
            ->assertDontSee('<style')
            ->assertDontSee('<link');

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

        $this->mock(WebDashboardService::class)
            ->shouldReceive('forUser')
            ->once()
            ->with($user, null, null)
            ->andReturn($this->pageData(null));

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Aún no tienes un semestre actual');
    }
}
