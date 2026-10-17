<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pruebas aisladas: solo SQLite en memoria. No modifican study_track de MySQL.
 * El cálculo real de SP se prueba aparte, con study_track_test.
 */
class ActivitiesApiTest extends TestCase
{
    private User $student;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite' ||
            config('database.connections.sqlite.database') !== ':memory:' ||
            !extension_loaded('pdo_sqlite')) {
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
        });
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('color_hex')->nullable();
            $table->decimal('current_average', 5, 2)->default(0);
            $table->integer('priority_level_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('priority_rules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('badge_label');
            $table->string('color_hex');
        });
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('activity_type')->default('task');
            $table->boolean('is_graded')->default(true);
            $table->decimal('points_possible', 5, 2)->default(0);
            $table->decimal('points_obtained', 5, 2)->nullable();
            $table->dateTime('due_datetime');
            $table->integer('estimated_minutes')->default(45);
            $table->string('status')->default('pending');
            $table->integer('priority_level_id')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('activity_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('event_datetime');
            $table->integer('calculated_weight')->default(10);
            $table->integer('priority_level_id')->nullable();
            $table->timestamps();
        });

        // El Observer sí se ejecuta; solo se simula su servicio para que
        // estos tests no dependan de los procedimientos de MariaDB.
        $this->mock(AnalyticsService::class)
            ->shouldReceive('refreshActivity')
            ->zeroOrMoreTimes();

        $this->student = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'clave']);
        $this->other = User::create(['name' => 'Luis', 'email' => 'luis@example.com', 'password' => 'clave']);
        DB::table('academic_semesters')->insert([
            ['id' => 1, 'user_id' => $this->student->id, 'name' => 'Semestre A'],
            ['id' => 2, 'user_id' => $this->other->id, 'name' => 'Semestre B'],
        ]);
        DB::table('courses')->insert([
            ['id' => 1, 'semester_id' => 1, 'name' => 'Matemática', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'semester_id' => 2, 'name' => 'Historia', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function task(int $courseId = 1): array
    {
        return [
            'title' => 'Resolver ejercicios',
            'activity_type' => 'task',
            'course_id' => $courseId,
            'is_graded' => true,
            'points_possible' => 25,
            'due_datetime' => '2026-11-01 10:00:00',
        ];
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/activities')->assertUnauthorized();
        $this->getJson('/api/calendar')->assertUnauthorized();
    }

    public function test_create_grade_complete_and_soft_delete(): void
    {
        Sanctum::actingAs($this->student);

        $created = $this->postJson('/api/activities', $this->task())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Resolver ejercicios');
        $id = $created->json('data.id');
        $this->assertSame($this->student->id, Activity::findOrFail($id)->user_id);

        $this->patchJson("/api/activities/{$id}/grade", ['points_obtained' => 20])
            ->assertOk()->assertJsonPath('data.points_obtained', '20.00');
        $this->patchJson("/api/activities/{$id}/complete")
            ->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertNotNull(Activity::find($id)->completed_at);

        $this->deleteJson("/api/activities/{$id}")->assertOk();
        $this->assertSoftDeleted('activities', ['id' => $id]);
    }

    public function test_user_cannot_read_modify_or_delete_other_students_activities(): void
    {
        $activity = Activity::withoutEvents(fn () => Activity::create(array_merge($this->task(2), [
            'user_id' => $this->other->id,
        ])));
        Sanctum::actingAs($this->student);

        $this->getJson("/api/activities/{$activity->id}")->assertForbidden();
        $this->patchJson("/api/activities/{$activity->id}", ['title' => 'Intruso'])->assertForbidden();
        $this->deleteJson("/api/activities/{$activity->id}")->assertForbidden();
        $this->getJson('/api/activities')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_rejects_other_users_course_and_inconsistent_grades(): void
    {
        Sanctum::actingAs($this->student);
        $this->postJson('/api/activities', $this->task(2))
            ->assertUnprocessable()->assertJsonValidationErrors('course_id');
        $this->postJson('/api/activities', array_merge($this->task(), ['points_obtained' => 30]))
            ->assertUnprocessable()->assertJsonValidationErrors('points_obtained');
        $this->postJson('/api/activities', [
            'title' => 'Trámite', 'activity_type' => 'administrative',
            'is_graded' => false, 'points_possible' => 10,
            'due_datetime' => '2026-11-01 12:00:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('points_possible');
    }

    public function test_administrative_activity_without_course_and_calendar_filters(): void
    {
        Sanctum::actingAs($this->student);
        $this->postJson('/api/activities', [
            'title' => 'Solicitud de constancia',
            'activity_type' => 'administrative',
            'due_datetime' => '2026-11-01 12:00:00',
        ])->assertCreated()->assertJsonPath('data.is_graded', false);

        DB::table('calendar_events')->insert([
            ['user_id' => $this->student->id, 'title' => 'Propio', 'event_datetime' => '2026-11-01 12:00:00', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $this->other->id, 'title' => 'Ajeno', 'event_datetime' => '2026-11-01 12:00:00', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $this->student->id, 'title' => 'Otro mes', 'event_datetime' => '2026-12-01 12:00:00', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->getJson('/api/calendar?from=2026-11-01&to=2026-11-30')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Propio');
    }
}
