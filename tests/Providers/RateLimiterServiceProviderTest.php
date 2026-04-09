<?php

namespace Tests\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimiterServiceProviderTest extends TestCase
{
    public function test_non_google_apps_script_request_uses_project_slug(): void
    {
        Config::set('epicollect.limits.api_export.entries', 1);
        Config::set('epicollect.limits.api_export.entries_google_apps_scripts', 1);

        $projectSlug = 'non-gas-' . uniqid();
        $limits = $this->resolveEntriesExportLimits($projectSlug, '10.10.10.10', 'curl/8.6.0');

        $this->assertCount(1, $limits);
        $this->assertSame($projectSlug, $limits[0]->key);
        $this->assertSame(1, $limits[0]->maxAttempts);
    }

    public function test_google_apps_script_requests_are_limited_by_shared_project_slug_key(): void
    {
        Config::set('epicollect.limits.api_export.entries', 100);
        Config::set('epicollect.limits.api_export.entries_google_apps_scripts', 1);

        $projectSlug = 'gas-shared-' . uniqid();
        $limits = $this->resolveEntriesExportLimits($projectSlug, '30.30.30.30', 'Google-Apps-Script');

        $this->assertCount(2, $limits);
        $this->assertSame($projectSlug, $limits[0]->key);
        $this->assertSame(100, $limits[0]->maxAttempts);
        $this->assertSame('google-apps-scripts|' . $projectSlug, $limits[1]->key);
        $this->assertSame(1, $limits[1]->maxAttempts);
    }

    public function test_google_apps_script_user_agent_match_is_case_insensitive(): void
    {
        Config::set('epicollect.limits.api_export.entries', 100);
        Config::set('epicollect.limits.api_export.entries_google_apps_scripts', 1);

        $projectSlug = 'gas-case-' . uniqid();
        $limits = $this->resolveEntriesExportLimits($projectSlug, '31.31.31.31', 'google-apps-script');

        $this->assertCount(2, $limits);
        $this->assertSame('google-apps-scripts|' . $projectSlug, $limits[1]->key);
        $this->assertSame(1, $limits[1]->maxAttempts);
    }

    public function test_google_apps_script_shared_limit_is_partitioned_by_project_slug(): void
    {
        Config::set('epicollect.limits.api_export.entries', 100);
        Config::set('epicollect.limits.api_export.entries_google_apps_scripts', 1);

        $projectSlugA = 'gas-project-a-' . uniqid();
        $projectSlugB = 'gas-project-b-' . uniqid();
        $limitsForProjectA = $this->resolveEntriesExportLimits($projectSlugA, '50.50.50.50', 'Google-Apps-Script');
        $limitsForProjectB = $this->resolveEntriesExportLimits($projectSlugB, '60.60.60.60', 'Google-Apps-Script');

        $this->assertCount(2, $limitsForProjectA);
        $this->assertCount(2, $limitsForProjectB);
        $this->assertSame('google-apps-scripts|' . $projectSlugA, $limitsForProjectA[1]->key);
        $this->assertSame('google-apps-scripts|' . $projectSlugB, $limitsForProjectB[1]->key);
        $this->assertNotSame($limitsForProjectA[1]->key, $limitsForProjectB[1]->key);
    }

    /**
     * @return array<int, Limit>
     */
    private function resolveEntriesExportLimits(string $projectSlug, string $ipAddress, string $userAgent): array
    {
        $request = Request::create('/api/export/' . $projectSlug, 'GET', [], [], [], [
            'REMOTE_ADDR' => $ipAddress,
            'HTTP_USER_AGENT' => $userAgent,
        ]);

        $request->setRouteResolver(function () use ($projectSlug) {
            return new readonly class ($projectSlug) {
                public function __construct(private string $projectSlug)
                {
                }

                public function parameter(string $name, mixed $default = null): mixed
                {
                    if ($name === 'project_slug') {
                        return $this->projectSlug;
                    }

                    return $default;
                }
            };
        });

        $limiter = RateLimiter::limiter('api-export-entries');

        $this->assertNotNull($limiter);

        $limits = $limiter($request);

        $this->assertIsArray($limits);

        return $limits;
    }
}
