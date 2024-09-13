<?php

namespace ec5\Providers;

use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //skip ide helper in production
        if ($this->app->isLocal()) {
            $this->app->register(IdeHelperServiceProvider::class);
        }

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
