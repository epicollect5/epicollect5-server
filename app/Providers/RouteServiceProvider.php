<?php

namespace ec5\Providers;

use Illuminate\Routing\Router;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'ec5\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        // Set particular guards on routes
        $this->app['router']->matched(function (\Illuminate\Routing\Events\RouteMatched $event) {
            $route = $event->route;
            if (!Arr::has($route->getAction(), 'guard')) {
                return;
            }
            $routeGuard = array_get($route->getAction(), 'guard');
            $this->app['auth']->resolveUsersUsing(function ($guard = null) use ($routeGuard) {
                return $this->app['auth']->guard($routeGuard)->user();
            });
            $this->app['auth']->setDefaultDriver($routeGuard);
        });

        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @param \Illuminate\Routing\Router $router
     * @return void
     */
    public function map(Router $router)
    {
        $this->mapWebRoutes($router);
        $this->mapApiInternalRoutes($router);
        $this->mapApiExternalRoutes($router);
    }

    /**
     * Define the "web" routes for the application.
     *
     * @param \Illuminate\Routing\Router $router
     * @return void
     */
    protected function mapWebRoutes(Router $router)
    {
        // We need to use the 'web' guard for web and api_internal requests, so they share the same session driver
        $router->group([
            'namespace' => $this->namespace,
            'middleware' => 'web',
            'guard' => 'web'
        ], function ($router) {
            require base_path('routes/web.php');
        });
    }

    /**
     * Define the "api internal" routes for the application.
     *
     * @param \Illuminate\Routing\Router $router
     * @return void
     */
    protected function mapApiInternalRoutes(Router $router)
    {
        //imp:
        //The `api_internal` guard is exactly like the `web` guard, so they share the same session driver
        //we pass 'web' (the default) so the calls to Auth::guard() works without parameters
        //however, api_internal as a guard does not work, have a look at that in the future
        //since the routing/auth was developed by someone who left,
        //old laravel version, maybe a bug, maybe this is related:
        //https://github.com/laravel/framework/issues/13788

        // or maybe this is the fix ->
        //https://stackoverflow.com/questions/63129820/middleware-not-working-when-created-route-from-routeserviceprovider-in-laravel
        $router->group([
            'namespace' => $this->namespace,
            'middleware' => 'api_internal',
            'guard' => 'web'
        ], function ($router) {
            require base_path('routes/api_internal.php');
        });
    }

    /**
     * Define the "api external" routes for the application.
     *
     * @param \Illuminate\Routing\Router $router
     * @return void
     */
    protected function mapApiExternalRoutes(Router $router)
    {
        $router->group([
            'namespace' => $this->namespace,
            'middleware' => 'api_external',
            'guard' => 'api_external'
        ], function ($router) {
            require base_path('routes/api_external.php');
        });
    }
}
