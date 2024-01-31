<?php

namespace ec5\Http\Controllers\Api\Project;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Validation\Project\RuleBulkImportUsers;
use ec5\Http\Validation\Project\RuleSwitchUserRole;
use ec5\Models\Project\ProjectRole;
use ec5\Models\User\User;
use ec5\Services\ProjectService;
use ec5\Traits\Requests\RequestAttributes;
use Illuminate\Http\Request;

class UserController
{
    use RequestAttributes;

    /**
     * return a json object with all the users belonging to a project by role
     **/
    public function all()
    {
        //todo bail out if not manager and up?
        $users = ProjectRole::getAllProjectMembers($this->requestedProject()->getId());
        $jsonUsers = [];

        foreach ($users as $user) {
            $jsonUsers[] = [
                'name' => $user->name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role' => $user->role
            ];
        }
        return response()->apiResponse($jsonUsers);
    }

    /**
     * Remove all the users of the specified role from the project
     */
    public function removeByRole(Request $request, ApiResponse $apiResponse)
    {
        // Only managers and up have access
        if (!$this->requestedProjectRole()->canManageUsers()) {
            //return error json
            return $apiResponse->errorResponse(404, ['manage-users' => ['ec5_91']]);
        }

        $role = $request->input('role');

        //validate 'role'
        //todo use a 'role' validation rule?
        if (!in_array($role, array_keys(config('epicollect.strings.project_roles')), true)) {
            //return error json
            return $apiResponse->errorResponse(404, ['manage-users' => ['ec5_91']]);
        }

        //creator roles cannot be removed!
        if ($role === config('epicollect.strings.project_roles.creator')) {
            //return error json
            return $apiResponse->errorResponse(404, ['manage-users' => ['ec5_91']]);
        }

        $projectRole = new ProjectRole();

        //get project id
        $projectId = $this->requestedProject()->getId();

        //get current logged-in user id (if a manager, cannot remove other managers)
        //curator and collector cannot access this feature
        $user = $this->requestedProjectRole()->getUser();
        $userRole = $this->requestedProjectRole()->getRole();

        if ($userRole === config('epicollect.strings.project_roles.manager')) {
            if ($role === config('epicollect.strings.project_roles.manager')) {
                //a manager cannot remove other managers, bail out
                return $apiResponse->errorResponse(404, ['manage-users' => ['ec5_91']]);
            }
        }

        //all good, try to remove users by role
        if ($projectRole->deleteByRole($projectId, $role, $user) >= 0) {
            //all good, rows deleted (if any)
            $apiResponse->setData(['message' => trans('status_codes.ec5_343', ['role' => ucfirst($role)])]);
            return $apiResponse->toJsonResponse(200);
        } else {
            //error response
            return $apiResponse->errorResponse(400, ['manage-users' => ['ec5_104']]);
        }
    }

    public function switchRole(
        Request            $request,
        ApiResponse        $apiResponse,
        RuleSwitchUserRole $ruleSwitchUserRole
    )
    {
        // Only managers and up have access
        if (!$this->requestedProjectRole()->canManageUsers()) {
            //return error json
            return $apiResponse->errorResponse(404, ['manage-users' => ['ec5_91']]);
        }

        $inputs = $request->all();

        $ruleSwitchUserRole->validate($inputs, true);

        if ($ruleSwitchUserRole->hasErrors()) {
            //return error json
            return $apiResponse->errorResponse(400, ['errors' => $ruleSwitchUserRole->errors()]);
        }

        $userToSwitchCurrentRole = $inputs['currentRole'];
        $userToSwitchNewRole = $inputs['newRole'];
        $email = $inputs['email'];
        $projectId = $this->requestedProject()->getId();

        $projectRole = new ProjectRole();

        //get the user having the email
        $userToSwitch = User::whereEmail($email)->first();

        //current active user (logged in)
        $requestedUser = $request->attributes->get('requestedUser');

        //check if the user is trying to change its own role, not possible
        $ruleSwitchUserRole->additionalChecks($requestedUser, $userToSwitch, $this->requestedProjectRole()->getRole(), $userToSwitchNewRole, $userToSwitchCurrentRole);
        if ($ruleSwitchUserRole->hasErrors()) {
            return $apiResponse->errorResponse(400, $ruleSwitchUserRole->errors());
        }

        //all good, switch role ;)
        if ($projectRole->switchUserRole($projectId, $userToSwitch, $userToSwitchCurrentRole, $userToSwitchNewRole) >= 0) {
            //all good, role switched
            $apiResponse->setData(['message' => trans('status_codes.ec5_241')]);
            return $apiResponse->toJsonResponse(200);
        } else {
            //error response
            return $apiResponse->errorResponse(400, ['manage-users' => ['ec5_104']]);
        }
    }

    public function addUsersBulk(Request $request, ApiResponse $apiResponse, RuleBulkImportUsers $ruleBulkImportUsers, ProjectService $projectService)
    {
        $requestedUser = $request->attributes->get('requestedUser');
        $validationErrors = [];

        // Only creators and managers have access
        if (!$this->requestedProjectRole()->canManageUsers()) {
            return $apiResponse->errorResponse(404, ['manage-users' => ['ec5_91']]);
        }

        // Retrieve post data
        $payload = $request->all();

        // Validate the inputs
        $ruleBulkImportUsers->validate($payload);
        if ($ruleBulkImportUsers->hasErrors()) {
            //kick user out if anything is invalid, teach him a lesson.
            return $apiResponse->errorResponse(404, $ruleBulkImportUsers->errors());
        }

        $emails = $payload['emails'];
        $newRole = $payload['role'];

        foreach ($emails as $email) {
            // Retrieve the user whose role is to be added
            $userToAdd = User::where('email', '=', $email)->first();
            if (!$userToAdd) {
                $userToAdd = new User();
                $userToAdd->email = $email;
                $userToAdd->save();
            }
            // Attempt to get their existing role if they have one
            $userProjectRole = $projectService->getRole($userToAdd, $this->requestedProject()->getId());

            // Additional checks on the user against the user performing the action,
            // using the new role passed in and user's existing role, if available
            $ruleBulkImportUsers->additionalChecks($requestedUser, $userToAdd, $this->requestedProjectRole()->getRole(), $newRole,
                $userProjectRole->getRole());
            if ($ruleBulkImportUsers->hasErrors()) {
                $validationErrors[] = $ruleBulkImportUsers->errors();
            }

            //Got here without any errors? Add the user role then ;)
            if (!$ruleBulkImportUsers->hasErrors()) {
                if (!$projectService->addOrUpdateUserRole(
                    $userToAdd->id,
                    $this->requestedProjectRole(),
                    $payload['role']
                )) {
                    $validationErrors[] = ['db' => ['ec5_104']];
                }
            }
        }
        //were there any errors?
        if (sizeof($validationErrors) === 0) {
            // Send http status code 200, ok!
            $apiResponse->setData(['message' => trans('status_codes.ec5_345', ['role' => $newRole])]);
            return $apiResponse->toJsonResponse(200);
        } else {
            //warn user about errors (manager roles which cannot be switched)
            return $apiResponse->errorResponse(400, $validationErrors);
        }
    }
}
