<?php

namespace ec5\Http\Middleware;

use Closure;
use ec5\Traits\Middleware\MiddlewareTools;
use Illuminate\Support\Facades\Auth;
use Response;

class AdminAuthenticate
{
    use MiddlewareTools;

    /*
    |--------------------------------------------------------------------------
    | AdminAuthenticate
    |--------------------------------------------------------------------------
    |
    | This middleware checks the user is a logged in 'admin' before being allowed to proceed
    |
    */

    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->guest()) {
            if ($this->isJsonRequest($request)) {
                $errors = ['auth' => ['ec5_219']];
                return Response::apiErrorCode(404, $errors);
            } else {
                return redirect()->guest('login/admin');
            }
        }

        $user = Auth::guard($guard)->user();

        // Check if user is active
        if (!$user->isActive()) {
            // If not, log out and redirect to login page with error
            Auth::guard($guard)->logout();
            return redirect()->guest('login/admin')->withErrors(['ec5_212']);
        }

        // Redirect if not admin or super admin
        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            return redirect('/');
        }

        return $next($request);
    }
}
