<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;
use Config;

class RuleProjectRole extends ValidationBase
{
    protected $rules = [
        // Allowed roles that can be added for a user (note: 'creator' is not a role that can be assigned after project creation)
        'email' => 'required|email'
    ];

    /**
     * RuleProjectRole constructor.
     */
    public function __construct()
    {
        // Add only valid 'provider' auth methods ie not 'local'
        $authMethods = config('auth.auth_methods');
        $roles = config('epicollect.permissions.projects.roles.creator');
        $invalidAuthMethod = array_search('local', config('auth.auth_methods'));
        if ($invalidAuthMethod !== false) {
            unset($authMethods[$invalidAuthMethod]);
        }

        $this->rules['provider'] = 'in:' . implode($authMethods, ',');

        //role must be know by the system
        $this->rules['role'] = 'required|in:' . implode($roles, ',');
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
    public function additionalChecks($adminUser, $user, $adminUserRole, $newUserRole = null, $existingUserRole = null)
    {
        // We must have at least one role supplied
        if (!$newUserRole && !$existingUserRole) {
            $this->errors['user'] = ['ec5_90'];
            return;
        }

        // Check the $adminUser is not the user we're trying to update the role for
        // i.e. a user cannot change their own role
        if ($adminUser->id == $user->id) {
            $this->errors['user'] = ['ec5_217'];
            return;
        }

        // $adminUser must have a valid role
        if (!is_array(config('epicollect.permissions.projects.roles.' . $adminUserRole))) {
            $this->errors['user'] = ['ec5_91'];
            return;
        }

        if ($newUserRole) {
            // $adminUser can only perform actions against certain roles, set in permissions config
            if (!in_array($newUserRole, config('epicollect.permissions.projects.roles.' . $adminUserRole))) {
                $this->errors['user'] = ['ec5_91'];
                return;
            }
        }

        // If an existing role was passed in, check
        if ($existingUserRole) {
            // $requestedUser can only perform actions against certain roles, set in permissions config
            if (!in_array($existingUserRole, config('epicollect.permissions.projects.roles.' . $adminUserRole))) {
                $this->errors['user'] = ['ec5_91'];
                return;
            }
        }
    }
}
