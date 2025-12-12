<?php

namespace ec5\Providers;

use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    private const string EPICOLLECT_MEDIA_BUCKET_PRODUCTION = 'epicollect5-media-bucket-production';
    private const string EXCEPTION_MESSAGE = 'Production media bucket is not allowed in this environment.';

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

        //Detect N+1 queries in non-production environments
        if (!app()->isProduction()) {
            Model::preventLazyLoading();
        }

        //skip ide helper in production
        if ($this->app->environment('development')) {
            $this->app->register(IdeHelperServiceProvider::class);
        }

        // Check if we are in production and using production bucket
        $host = request()->getHost() ?? ''; // Real-time HTTP host
        $bucket = config('filesystems.disks.s3.bucket');

        // If not in production domain but using production bucket, throw error
        if (config('epicollect.setup.system.storage_driver') === 's3') {
            if ($host !== 'five.epicollect.net' && $bucket === self::EPICOLLECT_MEDIA_BUCKET_PRODUCTION) {
                Log::error(__METHOD__ . ' failed.', ['exception' => self::EXCEPTION_MESSAGE]);
                throw new HttpException(500, self::EXCEPTION_MESSAGE);
            }
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
