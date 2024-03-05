<?php

namespace ec5\Http\Middleware;

use Illuminate\Http\Request;
use Closure;

class ProjectPermissionsOpen extends RequestAttributesMiddleware
{

    /*
    |--------------------------------------------------------------------------
    | ProjectPermissionsOpen Middleware
    |--------------------------------------------------------------------------
    |
    | This middleware handles project open link requests.
    |
    */

    public function handle(Request $request, Closure $next)
    {
        // Return the original request unchanged
        return $next($request);
    }

    //the open link is always public
    public function hasPermission(): bool
    {
        return true;
    }
}
