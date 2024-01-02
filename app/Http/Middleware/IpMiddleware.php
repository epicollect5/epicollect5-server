<?php

namespace ec5\Http\Middleware;

use Closure;
use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Traits\Middleware\MiddlewareTools;

class IpMiddleware
{
    use MiddlewareTools;

    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next)
    {
        $ips = config('auth.ip_whitelist');

        //localhost $request->ip() gives ::1, we filter only in production
        //for localhost testing, disable middleware as needed
        if (config('epicollect.setup.ip_filtering_enabled')) {
            if (!in_array($request->ip(), $ips)) {
                $errors = ['auth' => ['ec5_256']];
                $apiResponse = new ApiResponse();
                return $apiResponse->errorResponse(404, $errors);
            }
        }

        return $next($request);
    }
}
