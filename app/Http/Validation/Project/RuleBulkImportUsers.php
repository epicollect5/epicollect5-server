<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;
use Config;

class RuleBulkImportUsers extends ValidationBase
{
    protected $rules = [
        // Allowed roles that can be added for a user (note: 'creator' is not a role that can be assigned after project creation)
        'emails' => 'required|array|min:1|max:100',
        'emails.*' => 'required|email'
    ];

    public function __construct()
    {
        $emailsLimitMax = config('ec5Limits.emails_limit_max');
        $roles = Config::get('ec5Permissions.projects.roles.creator');

        $this->messages['required'] = 'ec5_21';
        $this->messages['emails.min'] = 'ec5_347';
        $this->messages['emails.max'] = trans('status_codes.ec5_346', ['limit' => $emailsLimitMax]);
        $this->messages['in'] = 'ec5_98';
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
        if (!is_array(Config::get('ec5Permissions.projects.roles.' . $adminUserRole))) {
            $this->errors['user'] = ['ec5_91'];
            return;
        }

        if ($newUserRole) {
            // $adminUser can only perform actions against certain roles, set in permissions config
            if (!in_array($newUserRole, Config::get('ec5Permissions.projects.roles.' . $adminUserRole))) {
                $this->errors['user'] = ['ec5_91'];
                return;
            }
        }

        // If an existing role was passed in, check
        if ($existingUserRole) {
            // $requestedUser can only perform actions against certain roles, set in permissions config
            if (!in_array($existingUserRole, Config::get('ec5Permissions.projects.roles.' . $adminUserRole))) {
                $this->errors['user'] = ['ec5_91'];
                return;
            }
        }
    }
}
