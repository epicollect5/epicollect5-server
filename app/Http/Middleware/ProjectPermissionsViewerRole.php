<?php

namespace ec5\Http\Middleware;

use Config;

class ProjectPermissionsViewerRole extends ProjectPermissionsBase
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
     * Check the given user/role has permission to access
     *
     * @return bool
     */
    public function hasPermission()
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
