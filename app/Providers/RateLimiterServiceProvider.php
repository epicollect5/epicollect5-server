<?php

namespace ec5\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
     * Configure all rate limiters for the application.
     */
    public function configureRateLimiters(): void
    {
        $this->configureAccountDeletionLimiter();
        $this->configurePasswordlessLimiter();
        $this->configureApiExportLimiters();
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
     * Configure a specific API export rate limiter.
     *
     * @param string $name The name of the rate limiter
     * @param string $configKey The config key for the rate limit
     */
    private function configureApiExportLimiter(string $name, string $configKey): void
    {
        RateLimiter::for($name, function (Request $request) use ($configKey) {
            return Limit::perMinute(
                config("epicollect.limits.api_export.$configKey")
            )->by($request->ip());
        });
    }
}
