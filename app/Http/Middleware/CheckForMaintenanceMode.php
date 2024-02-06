<?php

namespace ec5\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Exceptions\MaintenanceModeException;
use ec5\Traits\Middleware\MiddlewareTools;
use Response;

class CheckForMaintenanceMode
{

    use MiddlewareTools;

    /**
     * The application implementation.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * CheckForMaintenanceMode constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next)
    {
        if ($this->app->isDownForMaintenance()) {

            if ($this->isJsonRequest($request)) {
                $errors = ['maintenance.mode' => ['ec5_252']];
                return Response::apiErrorCode(404, $errors);
            } else {
                $data = json_decode(file_get_contents($this->app->storagePath() . '/framework/down'), true);
                throw new MaintenanceModeException($data['time'], $data['retry'], $data['message']);
            }
        }
        return $next($request);
    }
}
