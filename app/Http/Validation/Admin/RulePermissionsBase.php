<?php

namespace ec5\Http\Validation\Admin;

use ec5\Http\Validation\ValidationBase;
use Config;

class RulePermissionsBase extends ValidationBase
{

    /**
     * Additional checks
     *
     * @param $adminUser
     * @param $user
     */
    public function additionalChecks($adminUser, $user)
    {

        $this->usersCantUpdateThemselves($adminUser, $user);

        // $adminUser can only perform actions against certain roles, set in permissions config
        $this->userCanPerformAction($adminUser, $user);
    }

    /**
     * @param $adminUser
     * @param $user
     */
    public function usersCantUpdateThemselves($adminUser, $user)
    {
        // User cannot change their own state
        if ($adminUser->id == $user->id) {
            $this->errors['user'] = ['ec5_218'];
            return;
        }
    }

    /**
     * @param $adminUser
     * @param $user
     */
    public function userCanPerformAction($adminUser, $user)
    {
        if (!is_array(Config::get('ec5Permissions.server.roles.' . $adminUser->server_role)) ||
            !in_array($user->server_role, Config::get('ec5Permissions.server.roles.' . $adminUser->server_role))) {
            $this->errors['user'] = ['ec5_91'];
            return;
        }
    }
}
