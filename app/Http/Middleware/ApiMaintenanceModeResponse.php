<?php

namespace ec5\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use ec5\Traits\Middleware\MiddlewareTools;
use Response;

class ApiMaintenanceModeResponse
{
    use MiddlewareTools;

    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Handle an incoming API request and send JSON error
     * when the application is in maintenance mode
     */
    public function handle($request, Closure $next)
    {
        if ($this->app->isDownForMaintenance()) {
            if ($this->isJsonRequest($request)) {
                $errors = ['maintenance.mode' => ['ec5_252']];
                return Response::apiErrorCode(404, $errors);
            }
        }
        return $next($request);
    }
}
