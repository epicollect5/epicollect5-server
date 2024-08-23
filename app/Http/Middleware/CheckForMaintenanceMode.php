<?php

namespace ec5\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use ec5\Traits\Middleware\MiddlewareTools;
use Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
                throw new HttpException(503, $data['message'] ?? 'The site is currently under maintenance.', null, [], 0);
            }
        }
        return $next($request);
    }
}
