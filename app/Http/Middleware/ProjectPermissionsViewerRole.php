<?php

namespace ec5\Http\Middleware;

use Illuminate\Http\Request;
use Closure;

class ProjectPermissionsViewerRole extends RequestAttributesMiddleware
{
    /*
     |--------------------------------------------------------------------------
     | ProjectPermissionsViewerRole Middleware
     |--------------------------------------------------------------------------
     |
     | This middleware handles project requests.
     | A viewer role has limited access
     |
     */

    /**
     *
     * imp: doing this to avoid duplicated parsing of multipart request
     * @see RequestAttributesMiddleware::getParsedJsonInMultipart();
     */
    public function handle(Request $request, Closure $next)
    {
        // Return the original request unchanged
        return $next($request);
    }

    /**
     * Check the given user/role has permission to access
     *
     * @return bool
     */
    public function hasPermission(): bool
    {
        $viewerRole = config('epicollect.strings.project_roles.viewer');

        // Only need to check for a user/role if the project is private
        if ($this->requestedProject->isPrivate()) {

            //if role but only "viewer", kick user out
            if ($this->requestedProjectRole->getRole() === $viewerRole) {
                $this->error = 'ec5_91';
                return false;
            }
        }

        return true;
    }
}
