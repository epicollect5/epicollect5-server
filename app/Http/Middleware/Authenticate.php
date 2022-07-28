<?php

namespace ec5\Http\Middleware;

use Illuminate\Support\Facades\Auth;
use Closure;

class Authenticate extends MiddlewareBase
{
    /*
    |--------------------------------------------------------------------------
    | Authenticate
    |--------------------------------------------------------------------------
    |
    | This middleware checks the user is logged in before being allowed to proceed
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
        if (Auth::guard($guard)->guest()) {
            if ($this->isJsonRequest($request)) {
                $errors = ['auth' => ['ec5_219']];
                return $this->apiResponse->errorResponse(404, $errors);
            } else {
                return redirect()->guest('login');
            }
        }

        $user = Auth::guard($guard)->user();

        // Check if user is local and unverified
        if ($user->isLocalAndUnverified()) {
            //ok, send the user to verification page
            return redirect('signup/verification');
        }

        // Check if user is active
        if (!$user->isActive()) {
            // If not, log out and redirect to login page with error
            Auth::guard($guard)->logout();
            return redirect()->guest('login')->withErrors(['ec5_212']);
        }

        return $next($request);
    }
}
