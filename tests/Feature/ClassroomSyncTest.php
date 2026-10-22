<?php

namespace Tests\Feature;

use App\Models\ClassroomConnection;
use App\Models\ClassroomCourseImport;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\GoogleClassroomService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClassroomSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || !extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Estas pruebas requieren SQLite en memoria; nunca usan la base real.');
        }

        Schema::create('classroom_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('google_email');
            $table->text('refresh_token');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
        Schema::create('classroom_course_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('google_course_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('section')->nullable();
            $table->string('course_state');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'google_course_id']);
        });
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('semester_id')->default(1);
            $table->string('name');
            $table->string('classroom_course_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('activity_type')->default('task');
            $table->boolean('is_graded')->default(true);
            $table->decimal('points_possible', 5, 2)->default(0);
            $table->decimal('points_obtained', 5, 2)->nullable();
            $table->dateTime('due_datetime')->nullable();
            $table->integer('estimated_minutes')->default(45);
            $table->string('status')->default('pending');
            $table->string('classroom_coursework_id')->nullable();
            $table->string('classroom_submission_state')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function test_sync_lists_provisioned_courses_and_fetches_submissions_in_bounded_batches(): void
    {
        $user = new User();
        $user->id = 12;
        ClassroomConnection::create([
            'user_id' => $user->id,
            'google_email' => 'student@example.com',
            'refresh_token' => 'refresh-token',
        ]);

        $states = ['ACTIVE', 'ARCHIVED', 'PROVISIONED'];
        foreach ($states as $stateIndex => $state) {
            for ($number = 1; $number <= 2; $number++) {
                $courseNumber = $stateIndex * 2 + $number;
                ClassroomCourseImport::create([
                    'user_id' => $user->id,
                    'google_course_id' => "course-{$courseNumber}",
                    'name' => "Curso {$courseNumber}",
                    'course_state' => $state,
                    'status' => $state === 'PROVISIONED' ? 'pending' : 'imported',
                    'course_id' => $state === 'PROVISIONED' ? null : $courseNumber,
                ]);
                if ($state !== 'PROVISIONED') {
                    \Illuminate\Support\Facades\DB::table('courses')->insert([
                        'id' => $courseNumber,
                        'semester_id' => 1,
                        'name' => "Curso {$courseNumber}",
                        'classroom_course_id' => "course-{$courseNumber}",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $requests = [];
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use (&$requests, $states) {
            $requests[] = $request->url();
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if ($path === '/token') {
                return Http::response(['access_token' => 'access-token'], 200);
            }

            if ($path === '/v1/courses') {
                $stateIndex = array_search($query['courseStates'], $states, true);
                $courses = [];
                for ($number = 1; $number <= 2; $number++) {
                    $courseNumber = $stateIndex * 2 + $number;
                    $courses[] = [
                        'id' => "course-{$courseNumber}",
                        'name' => "Curso {$courseNumber}",
                        'section' => $courseNumber === 1
                            ? 'Administración de los recursos de Tecnología de la Información para garantizar la continuidad del negocio.'
                            : null,
                        'courseState' => $query['courseStates'],
                    ];
                }

                return Http::response(['courses' => $courses], 200);
            }

            if (preg_match('~/v1/courses/course-(\d+)/courseWork$~', $path, $matches)) {
                return Http::response(['courseWork' => [[
                    'id' => "work-{$matches[1]}",
                    'title' => "Tarea {$matches[1]}",
                    'maxPoints' => 10,
                ]]], 200);
            }

            if (preg_match('~/v1/courses/course-\d+/courseWork/work-(\d+)/studentSubmissions$~', $path, $matches)) {
                return Http::response(['studentSubmissions' => [[
                    'state' => 'RETURNED',
                    'assignedGrade' => 8,
                ]]], 200);
            }

            return Http::response(['error' => ['message' => 'Unexpected Classroom request']], 404);
        });

        $this->mock(AnalyticsService::class)
            ->shouldReceive('refreshAll')
            ->once()
            ->with($user);

        $result = app(GoogleClassroomService::class)->sync($user);

        $this->assertSame([
            'courses_found' => 6,
            'pending_courses' => 2,
            'activities_synced' => 4,
        ], $result);
        $this->assertSame(3, count(array_filter(
            $requests,
            fn (string $url) => str_contains($url, '/v1/courses?courseStates='),
        )));
        $this->assertSame(4, count(array_filter(
            $requests,
            fn (string $url) => str_contains($url, '/studentSubmissions?'),
        )));
        $this->assertSame(4, \Illuminate\Support\Facades\DB::table('activities')->count());
        $this->assertSame(
            'Administración de los recursos de Tecnología de la Información para garantizar la continuidad del negocio.',
            ClassroomCourseImport::where('google_course_id', 'course-1')->value('code'),
        );
    }
}
