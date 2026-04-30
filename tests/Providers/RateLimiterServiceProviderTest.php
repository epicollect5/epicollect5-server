<?php

namespace Tests\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RateLimiterServiceProviderTest extends TestCase
{
    public function test_non_google_ecosystem_request_uses_project_slug(): void
    {
        Config::set('epicollect.limits.api_export.entries', 1);
        Config::set('epicollect.limits.api_export.entries_google_apps_scripts', 1);

        $projectSlug = 'non-google-' . uniqid();
        // A standard browser or curl request
        $limits = $this->resolveEntriesExportLimits($projectSlug, '10.10.10.10', 'curl/8.6.0');

        $this->assertCount(1, $limits);
        $this->assertSame($projectSlug, $limits[0]->key);
        $this->assertSame(1, $limits[0]->maxAttempts);
    }

    public function test_public_media_requests_use_project_slug(): void
    {
        Config::set('epicollect.limits.api_external.media', 7);

        $projectSlug = 'media-project-' . uniqid();
        $limits = $this->resolvePublicMediaLimits($projectSlug, '10.10.10.10', 'curl/8.6.0');

        $this->assertCount(1, $limits);
        $this->assertSame($projectSlug, $limits[0]->key);
        $this->assertSame(7, $limits[0]->maxAttempts);
    }

    public function test_google_apps_script_requests_are_limited_by_shared_project_slug_key(): void
    {
        Config::set('epicollect.limits.api_export.entries', 100);
        Config::set('epicollect.limits.api_export.entries_google_apps_scripts', 1);

        $projectSlug = 'gas-shared-' . uniqid();
        $limits = $this->resolveEntriesExportLimits($projectSlug, '30.30.30.30', 'Google-Apps-Script');

        $this->assertCount(2, $limits);
        $this->assertSame('google-apps-scripts|' . $projectSlug, $limits[1]->key);
    }

    public function test_google_sheets_importdata_requests_are_identified_correctly(): void
    {
        Config::set('epicollect.limits.api_export.entries', 100);
        Config::set('epicollect.limits.api_export.entries_google_apps_scripts', 1);

        $projectSlug = 'sheets-shared-' . uniqid();
        // This is the specific UA found in the logs for =IMPORTDATA()
        $userAgent = 'Mozilla/5.0 (compatible; GoogleDocs; apps-spreadsheets; +http://docs.google.com)';

        $limits = $this->resolveEntriesExportLimits($projectSlug, '74.125.210.132', $userAgent);

        $this->assertCount(2, $limits, 'Google Sheets requests should trigger the dual-limit logic.');
        $this->assertSame('google-apps-scripts|' . $projectSlug, $limits[1]->key);
        $this->assertSame(1, $limits[1]->maxAttempts);
    }

    #[DataProvider('googleUserAgentProvider')]
    public function test_google_ecosystem_user_agent_match_is_case_insensitive(string $userAgent): void
    {
        Config::set('epicollect.limits.api_export.entries', 100);
        Config::set('epicollect.limits.api_export.entries_google_apps_scripts', 1);

        $projectSlug = 'google-case-' . uniqid();
        $limits = $this->resolveEntriesExportLimits($projectSlug, '31.31.31.31', $userAgent);

        $this->assertCount(2, $limits);
        $this->assertSame('google-apps-scripts|' . $projectSlug, $limits[1]->key);
    }

    public static function googleUserAgentProvider(): array
    {
        return [
            'Standard GAS'          => ['Google-Apps-Script'],
            'Lowercase GAS'         => ['google-apps-script'],
            'GoogleDocs Sheets'     => ['GoogleDocs'],
            'Lowercase Sheets'      => ['googledocs'],
            'Apps Spreadsheets'     => ['apps-spreadsheets'],
            'Mixed Case Sheets'     => ['Apps-SpreadSheets'],
        ];
    }

    public function test_google_shared_limit_is_partitioned_by_project_slug(): void
    {
        Config::set('epicollect.limits.api_export.entries', 100);
        Config::set('epicollect.limits.api_export.entries_google_apps_scripts', 1);

        $projectSlugA = 'google-project-a-' . uniqid();
        $projectSlugB = 'google-project-b-' . uniqid();

        $limitsForProjectA = $this->resolveEntriesExportLimits($projectSlugA, '50.50.50.50', 'GoogleDocs');
        $limitsForProjectB = $this->resolveEntriesExportLimits($projectSlugB, '60.60.60.60', 'apps-spreadsheets');

        $this->assertCount(2, $limitsForProjectA);
        $this->assertCount(2, $limitsForProjectB);
        $this->assertSame('google-apps-scripts|' . $projectSlugA, $limitsForProjectA[1]->key);
        $this->assertSame('google-apps-scripts|' . $projectSlugB, $limitsForProjectB[1]->key);
    }

    /**
     * @return array<int, Limit>
     */
    private function resolveEntriesExportLimits(string $projectSlug, string $ipAddress, string $userAgent): array
    {
        return $this->resolveProjectScopedLimits(
            'api-export-entries',
            '/api/export/entries/' . $projectSlug,
            $projectSlug,
            $ipAddress,
            $userAgent
        );
    }

    /**
     * @return array<int, Limit>
     */
    private function resolvePublicMediaLimits(string $projectSlug, string $ipAddress, string $userAgent): array
    {
        return $this->resolveProjectScopedLimits(
            'api-media',
            '/api/media/' . $projectSlug,
            $projectSlug,
            $ipAddress,
            $userAgent
        );
    }

    /**
     * @return array<int, Limit>
     */
    private function resolveProjectScopedLimits(
        string $limiterName,
        string $uri,
        string $projectSlug,
        string $ipAddress,
        string $userAgent
    ): array {
        $request = Request::create($uri, 'GET', [], [], [], [
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

        $limiter = RateLimiter::limiter($limiterName);
        $this->assertNotNull($limiter);

        $limits = $limiter($request);
        $this->assertIsArray($limits);

        return $limits;
    }
}
