<?php

namespace ec5\Http\Middleware;

class ProjectPermissionsOpen extends RequestAttributesMiddleware
{
    /*
    |--------------------------------------------------------------------------
    | ProjectPermissionsOpen Middleware
    |--------------------------------------------------------------------------
    |
    | This middleware handles project open link requests.
    |
    */

    //the open link is always public
    public function hasPermission(): bool
    {
        return true;
    }
}
