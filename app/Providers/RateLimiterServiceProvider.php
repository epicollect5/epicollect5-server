<?php

namespace ec5\Providers;

use App;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class RateLimiterServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     */
    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    /**
     * Sets up all application rate limiters for various features.
     *
     * Initializes rate limiters for account deletion, passwordless authentication, API exports, and OAuth token requests.
     */
    public function configureRateLimiters(): void
    {
        $this->configureAccountDeletionLimiter();
        $this->configurePasswordlessLimiter();
        $this->configureApiExportLimiters();
        $this->configureApiMediaLimiter();
        $this->configureOauthTokenLimiter();
        $this->configureBulkUploadLimiter();
    }

    /**
     * Configure rate limiter for bulk upload
     *  30 requests per minute by default, per user
     *  2x for IP, to account for shared IPs
     */
    private function configureBulkUploadLimiter(): void
    {
        $limit = config('epicollect.setup.api.rate_limit_per_minute.bulk_upload');
        RateLimiter::for('bulk-upload', function (Request $request) use ($limit) {

            if (App::environment('production')) {
                if (!$request->user()) {
                    //safety net just in case the user is not found, limit by IP only
                    return [
                        Limit::perMinute(2 * $limit)->by($request->ip())
                    ];
                }
                //If the user is found, limit by user and IP
                return [
                    //Limit per user (if authenticated, in development we might not have it)
                    Limit::perMinute($limit)->by($request->user()->id),
                    //Limit per IP, slightly higher for shared IPs
                    Limit::perMinute(2 * $limit)->by($request->ip())
                ];
            }

            //in development, check if we have a user
            if ($request->user()) {
                return [
                    Limit::perMinute($limit)->by($request->user()->id),
                    Limit::perMinute(2 * $limit)->by($request->ip())
                ];
            }

            //no user, use debug as user id
            return [
                Limit::perMinute($limit)->by('debug'),
                Limit::perMinute(2 * $limit)->by($request->ip())
            ];
        });
    }

    /**
     * Configure rate limiter for account deletion.
     */
    private function configureAccountDeletionLimiter(): void
    {
        RateLimiter::for('account-deletion', function (Request $request) {
            return Limit::perHour(
                config('epicollect.limits.account_deletion_limit')
            )->by($request->ip());
        });
    }

    /**
     * Configure rate limiter for passwordless authentication.
     */
    private function configurePasswordlessLimiter(): void
    {
        RateLimiter::for('passwordless', function (Request $request) {
            return Limit::perHour(
                config('epicollect.limits.passwordless_rate_limit')
            )->by($request->ip());
        });
    }

    /**
     * Configure all API export rate limiters.
     */
    private function configureApiExportLimiters(): void
    {
        $this->configureApiExportLimiter('api-export-project', 'project');
        $this->configureApiExportLimiter('api-export-entries', 'entries');
        $this->configureApiExportLimiter('api-export-media', 'media');
    }

    /**
     * Registers a rate limiter for API export operations with a configurable per-minute limit.
     *
     * @param string $name The unique identifier for the rate limiter.
     * @param string $configKey The configuration key specifying the rate limit value.
     */
    private function configureApiExportLimiter(string $name, string $configKey): void
    {
        // To avoid rotating IPs bypassing throttles, limit project-scoped read endpoints by project slug.
        RateLimiter::for($name, function (Request $request) use ($name, $configKey) {
            $limits = [
                Limit::perMinute(
                    config("epicollect.limits.api_export.$configKey")
                )->by($request->route('project_slug'))
            ];
            // If this is an entries export request from Google Ecosystem, we can apply a stricter limit to prevent abuse
            if ($name === 'api-export-entries' && $this->isGoogleEcosystemRequest($request)) {
                $limits[] = Limit::perMinute(
                    config('epicollect.limits.api_export.entries_google_apps_scripts', 10)
                )->by('google-apps-scripts'. '|' . $request->route('project_slug'));
            }

            return $limits;
        });
    }

    /**
     * Configure rate limiter for public media endpoints.
     */
    private function configureApiMediaLimiter(): void
    {
        // To avoid rotating IPs bypassing throttles, limit project-scoped media reads by project slug.
        RateLimiter::for('api-media', function (Request $request) {
            return [
                //per second to avoid bursts
                Limit::perSecond(
                    config('epicollect.limits.api_external.media')
                )->by($request->route('project_slug'))
            ];
        });
    }

    private function isGoogleEcosystemRequest(Request $request): bool
    {
        $userAgent = (string) $request->userAgent();

        // Catching all variants identified in the access logs
        return Str::contains($userAgent, [
            'Google-Apps-Script',
            'GoogleDocs',
            'apps-spreadsheets'
        ], true); // 'true' makes it case-insensitive
    }

    /**
     * Registers a rate limiter for the /api/oauth/token endpoint, restricting requests per hour per client IP based on configuration.
     */
    private function configureOauthTokenLimiter(): void
    {
        RateLimiter::for('oauth-token', function (Request $request) {
            return Limit::perHour(config('epicollect.limits.oauth_token_limit'))
                ->by($request->ip());
        });
    }
}
