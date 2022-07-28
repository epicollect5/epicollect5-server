<?php

namespace ec5\Http\Middleware;

use Illuminate\Support\Facades\Auth;
use Closure;

class BasicAuthenticate extends MiddlewareBase
{
    /*
    |--------------------------------------------------------------------------
    | BasicAuthenticate
    |--------------------------------------------------------------------------
    |
    | This middleware checks the user is a logged in 'basic' before being allowed to proceed
    |
    */

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param null $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->guest()) {
            if ($this->isJsonRequest($request)) {
                return response('Unauthorized.', 401);
            } else {
                return redirect()->guest('login');
            }
        }

        $user = Auth::guard($guard)->user();

        // Redirect if not admin or super admin
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return redirect('/');
        }

        return $next($request);
    }
}
