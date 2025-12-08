<?php

namespace ec5\Http\Middleware;

use Illuminate\Support\Facades\Auth;
use Closure;
use ec5\Traits\Middleware\MiddlewareTools;
use Response;


class Unverified
{
    use MiddlewareTools;

    /*
    |--------------------------------------------------------------------------
    | Unverified
    |--------------------------------------------------------------------------
    |
    | This middleware checks the user is logged in before being allowed to proceed
    | the user can proceed only if with unverified state
    |
    */

    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next, $guard = null)
    {
        //if the user in not logged in, bail
        if (Auth::guard($guard)->guest()) {
            if ($this->isJsonRequest($request)) {
                $errors = ['auth' => ['ec5_219']];
                return Response::apiErrorCode(404, $errors);
            } else {
                return redirect()->guest('login');
            }
        }

        $user = Auth::guard($guard)->user();

        // Check if user is unverified
        if (!$user->isUnverified()) {
            // If not, log out and redirect to login page with error
            return redirect('/');
        }
        return $next($request);
    }
}
