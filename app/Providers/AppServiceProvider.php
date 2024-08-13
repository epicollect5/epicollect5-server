<?php

namespace ec5\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //https://stackoverflow.com/questions/65003024/replicating-claims-as-headers-is-deprecated-and-will-removed-from-v4-0-laravel
        if (config('app.debug')) {
            error_reporting(E_ALL & ~E_USER_DEPRECATED);
        } else {
            error_reporting(0);
        }

        Paginator::useBootstrapThree();
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
