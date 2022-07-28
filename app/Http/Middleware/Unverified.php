<?php

namespace ec5\Http\Middleware;

use Illuminate\Support\Facades\Auth;
use Closure;
use Config;

class Unverified extends MiddlewareBase
{
    /*
    |--------------------------------------------------------------------------
    | Unverified
    |--------------------------------------------------------------------------
    |
    | This middleware checks the user is logged in before being allowed to proceed
    | the user can proceed only if with unverifed state
    |
    */

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @param  string|null $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        //if the user in not logged in, bail
        if (Auth::guard($guard)->guest()) {
            if ($this->isJsonRequest($request)) {
                $errors = ['auth' => ['ec5_219']];
                return $this->apiResponse->errorResponse(404, $errors);
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
