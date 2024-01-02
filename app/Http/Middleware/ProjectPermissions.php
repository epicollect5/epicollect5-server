<?php

namespace ec5\Http\Middleware;

class ProjectPermissions extends RequestAttributesMiddleware
{

    /*
    |--------------------------------------------------------------------------
    | ProjectPermissions Middleware
    |--------------------------------------------------------------------------
    |
    | This middleware handles project requests.
    | A valid required role is not required, except to access private projects.
    |
    */

    /**
     * Check the given user/role has permission to access
     */
    public function hasPermission(): bool
    {
        // Only need to check for a user/role if the project is private
        if ($this->requestedProject->isPrivate()) {

            // If no user
            if (!$this->requestedUser) {
                $this->error = 'ec5_77';
                return false;
            }
            // If no role
            if (!$this->requestedProjectRole->hasRole()) {
                $this->error = 'ec5_78';
                return false;
            }
        }

        return true;
    }
}
