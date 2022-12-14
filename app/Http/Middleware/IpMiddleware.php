<?php

namespace ec5\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Config;

class IpMiddleware extends MiddlewareBase
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $ips = Config::get('auth.ip_whitelist');

        //localhost $request->ip() gives ::1, we filter only in production
        //for localhost testing, disable middleware as needed
        if (env('IP_FILTERING_ENABLED')) {
            if (!in_array($request->ip(), $ips)) {
                $errors = ['auth' => ['ec5_256']];
                return $this->apiResponse->errorResponse(404, $errors);
            }
        }

        return $next($request);
    }
}
