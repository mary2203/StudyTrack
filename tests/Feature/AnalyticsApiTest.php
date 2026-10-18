<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    private User $student;

    private User $other;

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
            $table->timestamps();
        });
        Schema::create('academic_semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(true);
            $table->timestamps();
        });
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id');
            $table->string('name');
            $table->decimal('target_grade', 5, 2)->default(61);
            $table->decimal('current_average', 5, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('title');
            $table->boolean('is_graded')->default(true);
            $table->decimal('points_possible', 5, 2)->default(0);
            $table->decimal('points_obtained', 5, 2)->nullable();
            $table->dateTime('due_datetime');
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('entity_name');
            $table->unsignedBigInteger('entity_id');
            $table->string('action');
            $table->text('old_data')->nullable();
            $table->text('new_data')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        $this->student = User::create([
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'clave',
        ]);
        $this->other = User::create([
            'name' => 'Luis',
            'email' => 'luis@example.com',
            'password' => 'clave',
        ]);

        DB::table('academic_semesters')->insert([
            [
                'id' => 1,
                'user_id' => $this->student->id,
                'name' => 'Semestre A',
                'start_date' => now()->subMonth()->toDateString(),
                'end_date' => now()->addMonths(3)->toDateString(),
                'is_current' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'user_id' => $this->other->id,
                'name' => 'Semestre B',
                'start_date' => now()->subMonth()->toDateString(),
                'end_date' => now()->addMonths(3)->toDateString(),
                'is_current' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('courses')->insert([
            ['id' => 1, 'semester_id' => 1, 'name' => 'Matemática', 'target_grade' => 61, 'current_average' => 40, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'semester_id' => 2, 'name' => 'Historia ajena', 'target_grade' => 61, 'current_average' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_requires_authentication_and_accepts_only_supported_levels(): void
    {
        $this->getJson('/api/analytics/descriptive')->assertUnauthorized();

        Sanctum::actingAs($this->student);
        $this->getJson('/api/analytics/other')->assertNotFound();
    }

    public function test_exposes_all_four_levels_for_the_authenticated_students_semester(): void
    {
        Sanctum::actingAs($this->student);

        foreach (['descriptive', 'diagnostic', 'predictive', 'prescriptive'] as $level) {
            $this->getJson("/api/analytics/{$level}")
                ->assertOk()
                ->assertJsonPath('data.level', $level)
                ->assertJsonPath('data.semester.name', 'Semestre A');
        }

        $this->getJson('/api/analytics/descriptive')
            ->assertJsonPath('data.data.courses.0.name', 'Matemática');

        $this->getJson('/api/analytics/prescriptive')
            ->assertJsonPath('data.data.actions.0.risk_level', 'insufficient_data')
            ->assertJsonPath(
                'data.data.actions.0.actions.0',
                'Registra al menos una calificación para obtener una estimación de riesgo más informativa.'
            );
    }

    public function test_predictive_risk_counts_overdue_ungraded_points_as_zero(): void
    {
        DB::table('activities')->insert([
            [
                'user_id' => $this->student->id,
                'course_id' => 1,
                'title' => 'Examen calificado',
                'is_graded' => true,
                'points_possible' => 25,
                'points_obtained' => 10,
                'due_datetime' => now()->subDays(2),
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $this->student->id,
                'course_id' => 1,
                'title' => 'Actividad vencida',
                'is_graded' => true,
                'points_possible' => 10,
                'points_obtained' => null,
                'due_datetime' => now()->subDay(),
                'status' => 'overdue',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $this->student->id,
                'course_id' => 1,
                'title' => 'Actividad futura',
                'is_graded' => true,
                'points_possible' => 40,
                'points_obtained' => null,
                'due_datetime' => now()->addDays(3),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($this->student);
        $this->getJson('/api/analytics/predictive')
            ->assertOk()
            ->assertJsonPath('data.data.courses.0.current_average', 40)
            ->assertJsonPath('data.data.courses.0.required_average_on_remaining', 89.38)
            ->assertJsonPath('data.data.courses.0.overdue_ungraded_points_counted_as_zero', 10)
            ->assertJsonPath('data.data.courses.0.remaining_points', 40)
            ->assertJsonPath('data.data.courses.0.risk_level', 'medium');
    }

    public function test_predictive_distinguishes_unreachable_and_insufficient_data(): void
    {
        DB::table('activities')->insert([
            [
                'user_id' => $this->student->id,
                'course_id' => 1,
                'title' => 'Puntos ya evaluados',
                'is_graded' => true,
                'points_possible' => 50,
                'points_obtained' => 10,
                'due_datetime' => now()->subDays(2),
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $this->student->id,
                'course_id' => 1,
                'title' => 'Puntos perdidos',
                'is_graded' => true,
                'points_possible' => 40,
                'points_obtained' => null,
                'due_datetime' => now()->subDay(),
                'status' => 'overdue',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $this->student->id,
                'course_id' => 1,
                'title' => 'Última actividad',
                'is_graded' => true,
                'points_possible' => 10,
                'points_obtained' => null,
                'due_datetime' => now()->addDays(3),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($this->student);
        $this->getJson('/api/analytics/predictive')
            ->assertOk()
            ->assertJsonPath('data.data.courses.0.risk_level', 'high');

        DB::table('activities')->delete();
        DB::table('activities')->insert([
            'user_id' => $this->student->id,
            'course_id' => 1,
            'title' => 'Sin nota aún',
            'is_graded' => true,
            'points_possible' => 100,
            'points_obtained' => null,
            'due_datetime' => now()->addDays(3),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/analytics/predictive')
            ->assertOk()
            ->assertJsonPath('data.data.courses.0.risk_level', 'insufficient_data');
    }

    public function test_diagnostic_returns_only_the_authenticated_users_audit_events(): void
    {
        DB::table('audit_logs')->insert([
            [
                'user_id' => $this->student->id,
                'entity_name' => 'course',
                'entity_id' => 1,
                'action' => 'recalculated',
                'old_data' => json_encode(['current_average' => 50]),
                'new_data' => json_encode(['current_average' => 40]),
                'created_at' => now(),
            ],
            [
                'user_id' => $this->other->id,
                'entity_name' => 'course',
                'entity_id' => 2,
                'action' => 'recalculated',
                'old_data' => null,
                'new_data' => null,
                'created_at' => now(),
            ],
        ]);

        Sanctum::actingAs($this->student);
        $this->getJson('/api/analytics/diagnostic')
            ->assertOk()
            ->assertJsonCount(1, 'data.data.events')
            ->assertJsonPath('data.data.events.0.old_data.current_average', 50)
            ->assertJsonPath('data.data.events.0.new_data.current_average', 40);
    }
}
