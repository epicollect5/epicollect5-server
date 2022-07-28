<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;
use Config;

class RuleSwitchUserRole extends ValidationBase
{
    protected $rules = [
        'email' => 'required|email'
    ];

    /**
     * RuleProjectRole constructor.
     */
    public function __construct()
    {
        $this->messages['required'] = 'ec5_21';
        $this->messages['email'] = 'ec5_42';
        $this->messages['in'] = 'ec5_98';

        // Allowed roles that can be added for a user (note: 'creator' is not a role that can be assigned after project creation)
        $roles = Config::get('ec5Permissions.projects.roles.creator');
        $this->rules['currentRole'] = 'required|in:' . implode($roles, ',');
        $this->rules['newRole'] = 'required|in:' . implode($roles, ',');

    }

    /**
     * Additional validation checks that a user is allowed to change another user's role
     * We compare the new role and the existing role (if there is one), against the admin user's
     *
     * @param $adminUser
     * @param $user
     * @param $adminUserRole
     * @param $newUserRole
     * @param $existingUserRole
     */
    public function additionalChecks($currentActiveUser, $userToSwitch, $currentActiveUserRole, $userToSwitchNewRole = null, $userToSwitchCurrentRole)
    {
        // We must have at least one role supplied
        if (!($userToSwitchNewRole || $userToSwitchCurrentRole)) {
            $this->errors['user'] = ['ec5_90'];
            return;
        }

        // Check the $currentActiveUser is not the user we're trying to update the role for
        // i.e. a user cannot change their own role
        if ($currentActiveUser->id === $userToSwitch->id) {
            $this->errors['user'] = ['ec5_217'];
            return;
        }

        // $currentActiveUser must have a valid role
        if (!is_array(Config::get('ec5Permissions.projects.roles.' . $currentActiveUserRole))) {
            $this->errors['user'] = ['ec5_91'];
            return;
        }

        //check the user (creator or manager) has the permission to switch other user role
        //CREATOR can switch any role (aside from CREATOR)
        //MANAGER can switch curator and collector users only
        if ($userToSwitch) {
            if (!in_array($userToSwitchNewRole, Config::get('ec5Permissions.projects.roles.' . $currentActiveUserRole))) {
                $this->errors['user'] = ['ec5_91'];
                return;
            }

            /**
             * if current user is a manager, he cannot switch another manager role
             */
            if (!in_array($userToSwitchCurrentRole, Config::get('ec5Permissions.projects.roles.' . $currentActiveUserRole))) {
                $this->errors['user'] = ['ec5_91'];
                return;
            }
        }
    }
}
