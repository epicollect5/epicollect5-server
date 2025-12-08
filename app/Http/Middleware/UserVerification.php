<?php

namespace ec5\Http\Middleware;

use Closure;
use ec5\Exceptions\UserNotVerifiedException;

class UserVerification
{
    /**
     * Check local user account for verification
     * Google Account are verified by Google
     * @noinspection PhpUndefinedFieldInspection
     * @throws UserNotVerifiedException
     */
    public function handle($request, Closure $next)
    {
        if (!is_null($request->user()) && !$request->user()->verified) {

            //not verified? Check only if "local" user
            if ($request->user()->provider === config('epicollect.strings.providers.local')) {
                throw new UserNotVerifiedException();
            }
        }

        return $next($request);
    }
}
