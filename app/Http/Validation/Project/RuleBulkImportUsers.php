<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;
use ec5\Libraries\Utilities\Common;

class RuleBulkImportUsers extends ValidationBase
{
    protected array $rules = [
        // Allowed roles that can be added for a user (note: 'creator' is not a role that can be assigned after project creation)
        'emails' => 'required|array|min:1|max:100',
        'emails.*' => 'required|email'
    ];

    public function __construct()
    {
        $emailsLimitMax = config('epicollect.limits.emails_limit_max');
        $roles = config('epicollect.permissions.projects.roles.creator');

        $this->messages['required'] = 'ec5_21';
        $this->messages['emails.min'] = 'ec5_347';
        $this->messages['emails.max'] = Common::configWithParams(
            'epicollect.codes.ec5_346',
            ['limit' => $emailsLimitMax]
        );
        $this->messages['in'] = 'ec5_98';
        //role must be known by the system
        $this->rules['role'] = 'required|in:' . implode(',', $roles);
    }

    /**
     * Additional validation checks that a user is allowed to change another user's role
     * We compare the new role and the existing role (if there is one), against the admin user's
     *
     * @param $projectAdmin
     * @param $userToAdd
     * @param $projectAdminRole
     * @param null $userToAddRole
     * @param null $existingUserRole
     */
    public function additionalChecks($projectAdmin, $userToAdd, $projectAdminRole, $userToAddRole = null, $existingUserRole = null): void
    {
        // We must have at least one role supplied
        if (!$userToAddRole && !$existingUserRole) {
            $this->errors['user'] = ['ec5_90'];
            return;
        }

        // Check the $projectAdmin is not the user we're trying to update the role for
        // Basically a user cannot change their own role
        if ($projectAdmin->id == $userToAdd->id) {
            $this->errors['user'] = ['ec5_217'];
            return;
        }

        // $projectAdmin must have a valid role
        if (!is_array(config('epicollect.permissions.projects.roles.' . $projectAdminRole))) {
            $this->errors['user'] = ['ec5_91'];
            return;

        }

        if ($userToAddRole) {
            // $projectAdmin can only perform actions against certain roles, set in permissions config
            if (!in_array($userToAddRole, config('epicollect.permissions.projects.roles.' . $projectAdminRole))) {
                $this->errors['user'] = ['ec5_91'];
                return;
            }
        }

        // If an existing role was passed in, check
        if ($existingUserRole) {
            // $requestedUser can only perform actions against certain roles, set in permissions config
            if (!in_array($existingUserRole, config('epicollect.permissions.projects.roles.' . $projectAdminRole))) {
                $this->errors['user'] = ['ec5_91'];
            }
        }
    }
}
