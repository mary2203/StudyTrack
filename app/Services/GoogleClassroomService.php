<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ClassroomConnection;
use App\Models\ClassroomCourseImport;
use App\Models\Course;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleClassroomService
{
    private const BASE_URL = 'https://classroom.googleapis.com/v1';
    private const OAUTH_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPES = [
        'https://www.googleapis.com/auth/classroom.courses.readonly',
        'https://www.googleapis.com/auth/classroom.coursework.me.readonly',
        'https://www.googleapis.com/auth/classroom.student-submissions.me.readonly',
        'openid',
        'email',
    ];

    public function authorizationUrl(string $state): string
    {
        $this->assertConfigured();

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => config('services.google_classroom.client_id'),
            'redirect_uri' => config('services.google_classroom.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * @return array{refresh_token: string, email: string}
     */
    public function connect(string $code, ?string $existingRefreshToken = null): array
    {
        $response = Http::asForm()->post(self::OAUTH_URL, [
            'client_id' => config('services.google_classroom.client_id'),
            'client_secret' => config('services.google_classroom.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.google_classroom.redirect_uri'),
        ]);
        $tokens = $this->decodeResponse($response->status(), $response->json());
        if (empty($tokens['access_token'])) {
            throw new RuntimeException('Google no devolvió un token de acceso válido.');
        }
        $refreshToken = $tokens['refresh_token'] ?? $existingRefreshToken;

        if (!$refreshToken) {
            throw new RuntimeException('Google no devolvió un token de actualización. Revoca el acceso de StudyTrack en tu cuenta de Google e inténtalo de nuevo.');
        }

        $identity = Http::withToken($tokens['access_token'])
            ->get('https://openidconnect.googleapis.com/v1/userinfo');
        $user = $this->decodeResponse($identity->status(), $identity->json());

        if (empty($user['email'])) {
            throw new RuntimeException('Google no devolvió el correo de la cuenta autorizada.');
        }

        return ['refresh_token' => $refreshToken, 'email' => $user['email']];
    }

    public function revoke(ClassroomConnection $connection): void
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/revoke', [
            'token' => $connection->refresh_token,
        ]);

        if (!$response->successful() && $response->status() !== 400) {
            throw new RuntimeException('No se pudo revocar el acceso en Google. Inténtalo de nuevo.');
        }
    }

    public function sync(User $user): int
    {
        $connection = ClassroomConnection::where('user_id', $user->id)->firstOrFail();
        $accessToken = $this->accessToken($connection);
        $client = Http::withToken($accessToken)->acceptJson();
        $courses = array_merge(
            $this->listAll($client, self::BASE_URL . '/courses', ['courseStates' => 'ACTIVE']),
            $this->listAll($client, self::BASE_URL . '/courses', ['courseStates' => 'ARCHIVED']),
        );
        $syncedActivities = 0;
        $hasImportedCourses = false;

        foreach ($courses as $googleCourse) {
            if (empty($googleCourse['id']) || empty($googleCourse['name'])) {
                continue;
            }

            $state = $googleCourse['courseState'] ?? 'ACTIVE';
            $existing = ClassroomCourseImport::firstOrNew([
                'user_id' => $user->id,
                'google_course_id' => $googleCourse['id'],
            ]);
            $existing->fill([
                'name' => $googleCourse['name'],
                'code' => $googleCourse['section'] ?? null,
                'section' => $googleCourse['room'] ?? null,
                'course_state' => $state,
            ]);

            if ($existing->status === 'ignored') {
                $existing->save();
                continue;
            }

            if ($existing->status !== 'imported') {
                $existing->status = 'pending';
                $existing->save();
                continue;
            }
            $existing->save();

            if ($existing->status !== 'imported' || !$existing->course_id) {
                continue;
            }
            $hasImportedCourses = true;

            $course = Course::whereKey($existing->course_id)->first();
            if (!$course) {
                $course = Course::withTrashed()->whereKey($existing->course_id)->first();
                if ($course?->trashed()) {
                    $course->restore();
                }
            }
            if (!$course) {
                continue;
            }

            $workItems = $this->listAll(
                $client,
                self::BASE_URL . '/courses/' . rawurlencode($googleCourse['id']) . '/courseWork',
                ['courseWorkStates' => 'PUBLISHED'],
            );
            foreach ($workItems as $work) {
                if (empty($work['id']) || empty($work['title'])) {
                    continue;
                }

                $submission = $this->studentSubmission($client, $googleCourse['id'], $work['id']);
                $due = $this->dueDateTime($work);
                $isGraded = isset($work['maxPoints']) && (float) $work['maxPoints'] > 0;
                $submissionState = $submission['state'] ?? 'NEW';
                $status = in_array($submissionState, ['TURNED_IN', 'RETURNED'], true)
                    ? 'completed'
                    : 'pending';

                Activity::withoutEvents(function () use ($user, $work, $course, $isGraded, $submission, $due, $status, $submissionState) {
                    $activity = Activity::withTrashed()->updateOrCreate(
                        ['user_id' => $user->id, 'classroom_coursework_id' => $work['id']],
                        [
                            'course_id' => $course->id,
                            'title' => $work['title'],
                            'description' => $work['description'] ?? null,
                            'activity_type' => 'task',
                            'is_graded' => $isGraded,
                            'points_possible' => $isGraded ? (float) $work['maxPoints'] : 0,
                            'points_obtained' => $submission['assignedGrade'] ?? null,
                            'due_datetime' => $due,
                            'estimated_minutes' => 45,
                            'status' => $status,
                            'classroom_submission_state' => $submissionState,
                            'completed_at' => $status === 'completed' ? now() : null,
                        ],
                    );

                    if ($activity->trashed()) {
                        $activity->restore();
                    }
                });
                $syncedActivities++;
            }
        }

        $connection->update(['last_synced_at' => now()]);
        if ($hasImportedCourses) {
            app(AnalyticsService::class)->refreshAll($user);
        }

        return $syncedActivities;
    }

    private function accessToken(ClassroomConnection $connection): string
    {
        $response = Http::asForm()->post(self::OAUTH_URL, [
            'client_id' => config('services.google_classroom.client_id'),
            'client_secret' => config('services.google_classroom.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);
        $tokens = $this->decodeResponse($response->status(), $response->json());

        if (empty($tokens['access_token'])) {
            throw new RuntimeException('Google no devolvió un token de acceso válido.');
        }

        return $tokens['access_token'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listAll(PendingRequest $client, string $url, array $query = []): array
    {
        $items = [];
        $pageToken = null;

        do {
            $response = $client->get($url, $query + array_filter(['pageToken' => $pageToken]));
            $data = $this->decodeResponse($response->status(), $response->json());
            $items = array_merge(
                $items,
                $data['courses'] ?? $data['courseWork'] ?? $data['studentSubmissions'] ?? [],
            );
            $pageToken = $data['nextPageToken'] ?? null;
        } while ($pageToken);

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function studentSubmission(PendingRequest $client, string $courseId, string $courseWorkId): ?array
    {
        $url = self::BASE_URL . '/courses/' . rawurlencode($courseId)
            . '/courseWork/' . rawurlencode($courseWorkId) . '/studentSubmissions';
        $submissions = $this->listAll($client, $url, ['userId' => 'me']);

        return $submissions[0] ?? null;
    }

    private function dueDateTime(array $work): ?string
    {
        if (empty($work['dueDate'])) {
            return null;
        }

        $date = $work['dueDate'];
        $time = $work['dueTime'] ?? [];

        return Carbon::create(
            (int) $date['year'],
            (int) $date['month'],
            (int) $date['day'],
            (int) ($time['hours'] ?? 23),
            (int) ($time['minutes'] ?? 59),
            (int) ($time['seconds'] ?? 0),
            config('app.timezone'),
        )->toDateTimeString();
    }

    private function decodeResponse(int $status, ?array $body): array
    {
        if ($status < 200 || $status >= 300) {
            $error = $body['error'] ?? null;
            $message = $body['error_description']
                ?? (is_array($error) ? ($error['message'] ?? null) : $error)
                ?? "Google respondió con el código {$status}.";
            throw new RuntimeException('Error de Google Classroom: ' . $message);
        }

        return $body ?? [];
    }

    private function assertConfigured(): void
    {
        if (!config('services.google_classroom.client_id')
            || !config('services.google_classroom.client_secret')
            || !config('services.google_classroom.redirect_uri')) {
            throw new RuntimeException('Configura GOOGLE_CLASSROOM_CLIENT_ID, GOOGLE_CLASSROOM_CLIENT_SECRET y GOOGLE_CLASSROOM_REDIRECT_URI en el archivo .env.');
        }
    }
}
