<?php

namespace ec5\Http\Middleware;

use Closure;
use ec5\Http\Controllers\Api\ApiResponse;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Exceptions\MaintenanceModeException;

class CheckForMaintenanceMode extends MiddlewareBase
{
    /**
     * The application implementation.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * CheckForMaintenanceMode constructor.
     * @param Application $app
     * @param ApiResponse $apiResponse
     */
    public function __construct(Application $app, ApiResponse $apiResponse)
    {
        $this->app = $app;
        parent::__construct($apiResponse);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function handle($request, Closure $next)
    {
        if ($this->app->isDownForMaintenance()) {

            if ($this->isJsonRequest($request)) {
                $errors = ['maintenance.mode' => ['ec5_252']];
                return $this->apiResponse->errorResponse(404, $errors);
            } else {
                $data = json_decode(file_get_contents($this->app->storagePath().'/framework/down'), true);
                throw new MaintenanceModeException($data['time'], $data['retry'], $data['message']);
            }

        }

        return $next($request);
    }
}
