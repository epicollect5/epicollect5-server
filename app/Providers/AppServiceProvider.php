<?php

namespace ec5\Providers;

use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstraps key application services.
     *
     * This method conditionally registers the IDE helper when running locally, configures
     * a rate limiter for passwordless authentication based on the client's IP address and
     * application settings, sets the pagination style to Bootstrap 3, and disables Blade
     * component tags.
     *
     * @return void
     */
    public function boot(): void
    {
        //skip ide helper in production
        if ($this->app->isLocal()) {
            $this->app->register(IdeHelperServiceProvider::class);
        }

        //set rate limit for passworless authentication
        RateLimiter::for('passwordless', function (Request $request) {
            return Limit::perHour(
                config('epicollect.limits.passwordless_rate_limit')
            )->by(
                $request->ip()
            );
        });

        Paginator::useBootstrapThree();
        Blade::withoutComponentTags();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    }
}
