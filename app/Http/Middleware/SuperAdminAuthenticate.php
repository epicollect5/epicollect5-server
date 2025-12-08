<?php

namespace ec5\Http\Middleware;

use Illuminate\Support\Facades\Auth;
use Closure;
use ec5\Traits\Middleware\MiddlewareTools;
use Response;

class SuperAdminAuthenticate
{
    use MiddlewareTools;

    /*
    |--------------------------------------------------------------------------
    | SuperAdminAuthenticate
    |--------------------------------------------------------------------------
    |
    | This middleware checks the user is a logged in 'superadmin' before being allowed to proceed
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
                return redirect()->guest('login');
            }
        }

        $user = Auth::guard($guard)->user();

        // Check if user is active
        if (!$user->isActive()) {
            // If not, log out and redirect to login page with error
            Auth::guard($guard)->logout();
            return redirect()->guest('login/admin')->withErrors(['ec5_212']);
        }

        // Redirect if not super admin
        if (!$user->isSuperAdmin()) {
            return redirect('/');
        }

        return $next($request);
    }
}
