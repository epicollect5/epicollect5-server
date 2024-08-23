<?php

namespace ec5\Http\Validation\Admin;

use ec5\Http\Validation\ValidationBase;

class RulePermissionsBase extends ValidationBase
{
    /**
     * Additional checks
     *
     * @param $adminUser
     * @param $user
     */
    public function additionalChecks($adminUser, $user): void
    {

        $this->usersCantUpdateThemselves($adminUser, $user);

        // $adminUser can only perform actions against certain roles, set in permissions config
        $this->userCanPerformAction($adminUser, $user);
    }

    /**
     * @param $adminUser
     * @param $user
     */
    public function usersCantUpdateThemselves($adminUser, $user): void
    {
        // User cannot change their own state
        if ($adminUser->id == $user->id) {
            $this->errors['user'] = ['ec5_218'];
        }
    }

    /**
     * @param $adminUser
     * @param $user
     */
    public function userCanPerformAction($adminUser, $user): void
    {
        if (!is_array(config('epicollect.permissions.server.roles.' . $adminUser->server_role)) ||
            !in_array($user->server_role, config('epicollect.permissions.server.roles.' . $adminUser->server_role))) {
            $this->errors['user'] = ['ec5_91'];
        }
    }
}
