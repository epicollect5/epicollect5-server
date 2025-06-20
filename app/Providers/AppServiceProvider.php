<?php

namespace ec5\Providers;

use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstraps core application services and enforces environment-specific configuration.
     *
     * Registers the IDE helper service provider in local environments, ensures the production media bucket is not used outside the production domain, sets pagination to use Bootstrap 3, and disables Blade component tags globally.
     *
     * @return void
     * @throws Throwable If the production media bucket is configured outside the production domain.
     */
    public function boot(): void
    {
        //skip ide helper in production
        if ($this->app->isLocal()) {
            $this->app->register(IdeHelperServiceProvider::class);
        }

        // Check if we are in production and using production bucket
        $host = request()->getHost() ?? ''; // Real-time HTTP host
        $bucket = config('filesystems.disks.project_thumb.bucket');
        $productionBucket = 'epicollect5-production-media-space';

        // If not in production domain but using production bucket, throw error
        if (!str_contains($host, 'five.epicollect.net') && $bucket === $productionBucket) {
            Log::error(__METHOD__ . ' failed.', ['exception' => 'Forbidden: Production media bucket is not allowed in this environment.']);
            throw new HttpException(500);
        }

        Paginator::useBootstrapThree();
        Blade::withoutComponentTags();
    }

    /**
     * Register any application services.
     *
     * Rate limiting is configured in the RateLimiterServiceProvider
     * @see RateLimiterServiceProvider
     */
    public function register(): void
    {
    }
}
