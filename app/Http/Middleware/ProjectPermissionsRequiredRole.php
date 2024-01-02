<?php

namespace ec5\Http\Middleware;

class ProjectPermissionsRequiredRole extends RequestAttributesMiddleware
{

    /*
     |--------------------------------------------------------------------------
     | ProjectPermissionsRequiredRole Middleware
     |--------------------------------------------------------------------------
     |
     | This middleware handles project requests.
     | A valid role is required to access any project.
     |
     */

    /**
     * Check the given user/role has permission to access
     *
     * @return bool
     */
    public function hasPermission()
    {
        // Always check for a user/role
        // If no user
        if (!$this->requestedUser) {
            $this->error = 'ec5_70';
            return false;
        }
        // If no role
        if (!$this->requestedProjectRole->hasRole()) {
            $this->error = 'ec5_71';
            return false;
        }

        return true;
    }
}
