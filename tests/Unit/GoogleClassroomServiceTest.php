<?php

namespace Tests\Unit;

use App\Services\GoogleClassroomService;
use RuntimeException;
use Tests\TestCase;

class GoogleClassroomServiceTest extends TestCase
{
    public function test_oauth_url_uses_read_only_classroom_scopes_and_state(): void
    {
        config([
            'services.google_classroom.client_id' => 'test-client-id',
            'services.google_classroom.client_secret' => 'test-client-secret',
            'services.google_classroom.redirect_uri' => 'https://studytrack.test/classroom/callback',
        ]);

        $url = app(GoogleClassroomService::class)->authorizationUrl('csrf-state');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('csrf-state', $query['state']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent', $query['prompt']);
        $this->assertContains(
            'https://www.googleapis.com/auth/classroom.courses.readonly',
            explode(' ', $query['scope']),
        );
        $this->assertContains(
            'https://www.googleapis.com/auth/classroom.coursework.me.readonly',
            explode(' ', $query['scope']),
        );
        $this->assertContains(
            'https://www.googleapis.com/auth/classroom.student-submissions.me.readonly',
            explode(' ', $query['scope']),
        );
    }

    public function test_oauth_url_requires_google_cloud_credentials(): void
    {
        config([
            'services.google_classroom.client_id' => null,
            'services.google_classroom.client_secret' => null,
            'services.google_classroom.redirect_uri' => null,
        ]);

        $this->expectException(RuntimeException::class);
        app(GoogleClassroomService::class)->authorizationUrl('csrf-state');
    }
}
