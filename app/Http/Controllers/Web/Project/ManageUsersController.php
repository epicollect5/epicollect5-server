<?php /** @noinspection DuplicatedCode */

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Validation\Project\RuleEmail;
use ec5\Http\Validation\Project\RuleProjectRole;
use ec5\Models\Project\ProjectRole;
use ec5\Models\User\User;
use ec5\Services\Project\ProjectService;
use ec5\Traits\Requests\RequestAttributes;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Log;
use Response;
use Throwable;

class ManageUsersController
{
    use RequestAttributes;

    /**
     * @param ProjectService $projectService
     * @return Factory|Application|JsonResponse|RedirectResponse|View
     * @throws Throwable
     */
    public function index(ProjectService $projectService)
    {
        // Only managers (and creators) have access
        if (!$this->requestedProjectRole()->canManageUsers()) {
            return Redirect::back()->withErrors(['ec5_91']);
        }

        // Get request data
        $params = request()->all();
        // Set per page limit
        $perPage = config('epicollect.limits.users_per_page');
        // Set search/roles/current page option defaults
        $search = !empty($params['search']) ? $params['search'] : '';
        $roles = ['page-manager' => 'manager', 'page-curator' => 'curator', 'page-collector' => 'collector', 'page-viewer' => 'viewer'];
        $options = ['roles' => []];

        foreach ($roles as $paramKey => $role) {
            if (!empty($params[$paramKey])) {
                $options['roles'] = [$role];
                break;
            }
        }

        if (empty($options['roles'])) {
            $options['roles'] = array_keys(config('epicollect.permissions.projects.roles'));
        }

        // Set project id in options
        $options['project_id'] = $this->requestedProject()->getId();

        // Get paginated project users, based on per page, specified roles, current page and search term
        $projectMembers = $projectService->getProjectMembersPaginated($perPage, $search, $options);

        //CREATOR role can transfer ownership
        $canTransferOwnership = $this->requestedProjectRole()->isCreator();

        //superadmin can transfer ownership
        if ($this->requestedProjectRole()->getUser()->isSuperAdmin()) {
            $canTransferOwnership = true;
        }

        //admin can transfer ownership
        if ($this->requestedProjectRole()->getUser()->isAdmin()) {
            $canTransferOwnership = true;
        }

        // If ajax, return rendered html
        if (request()->ajax()) {
            // For ajax, we only want to return one rendered view
            // and one set of users for the specified role
            $projectUsers = $projectMembers[$options['roles'][0]];

            return response()->json(view(
                'project.project_users',
                [
                    'project' => $this->requestedProject(),
                    'projectUsers' => $projectUsers,
                    'requestedProjectRole' => $this->requestedProjectRole(),
                    'canTransferOwnership' => $canTransferOwnership,
                    'key' => $options['roles'][0]
                ]
            )
                ->render());
        }

        // Return only valid 'provider' auth methods i.e., not 'local'
        $authMethods = config('auth.auth_methods');
        $invalidAuthMethod = array_search('local', config('auth.auth_methods'));
        if ($invalidAuthMethod !== false) {
            unset($authMethods[$invalidAuthMethod]);
        }

        $projectRole = new ProjectRole();
        $countByRole = $projectRole->getCountByRole($this->requestedProject()->getId());
        $countOverall = $projectRole->getCountOverall($this->requestedProject()->getId());

        return view(
            'project.project_details',
            [
                'includeTemplate' => 'manage-users',
                'project' => $this->requestedProject(),
                'users' => $projectMembers,
                'countOverall' => $countOverall->total,
                'countByRole' => $countByRole,
                'requestedProjectRole' => $this->requestedProjectRole(),
                'canTransferOwnership' => $canTransferOwnership,
                'authMethods' => $authMethods
            ]
        );
    }

    /**
     * Attempt to add/edit a project role for a user
     * If the current user is creator/manager,
     * they cannot change their own role
     *
     * @param RuleProjectRole $ruleProjectRole
     * @param ProjectService $projectService
     * @return JsonResponse|RedirectResponse
     */
    public function addUserRole(RuleProjectRole $ruleProjectRole, ProjectService $projectService)
    {
        // Only creators and managers have access
        if (!$this->requestedProjectRole()->canManageUsers()) {
            // If ajax, return error json
            if (request()->ajax()) {
                return Response::apiErrorCode(404, ['manage-users' => ['ec5_91']]);
            }
            return Redirect::back()->withErrors(['ec5_91']);
        }

        $payload = request()->all();
        $ruleProjectRole->validate($payload);
        if ($ruleProjectRole->hasErrors()) {
            // If ajax, return error json
            if (request()->ajax()) {
                return Response::apiErrorCode(400, $ruleProjectRole->errors());
            }
            return Redirect::back()->withErrors($ruleProjectRole->errors());
        }

        // Retrieve the user whose role is to be added
        $userToAdd = User::where('email', '=', $payload['email'])->first();
        if (!$userToAdd) {
            // If no user, add a placeholder user to the system,
            //other fields will be filled in when the user logs in
            $userToAdd = new User();
            $userToAdd->email = $payload['email'];
            $userToAdd->save();
        }

        // Attempt to get their existing role if they have one
        $userToAddProjectRole = $projectService->getRole($userToAdd, $this->requestedProject()->getId());

        // Additional checks on the user against the user performing the action,
        // using the new role passed in and user's existing role, if available
        $ruleProjectRole->additionalChecks(
            $this->requestedUser(),
            $userToAdd,
            $this->requestedProjectRole()->getRole(),
            $payload['role'],
            $userToAddProjectRole->getRole()
        );
        if ($ruleProjectRole->hasErrors()) {
            // If ajax, return error json
            if (request()->ajax()) {
                return Response::apiErrorCode(400, $ruleProjectRole->errors());
            }
            return Redirect::back()->withErrors($ruleProjectRole->errors());
        }


        if (!$projectService->addOrUpdateUserRole(
            $userToAdd->id,
            $this->requestedProject()->getId(),
            $payload['role']
        )) {
            // If ajax, return error json
            if (request()->ajax()) {
                return Response::apiErrorCode(400, ['db' => ['ec5_104']]);
            }
            return Redirect::back()->withErrors(['db' => ['ec5_104']]);
        }

        // If ajax, return success json
        if (request()->ajax()) {
            // Send http status code 200, ok!
            $data = ['message' => config('epicollect.codes.ec5_88')];
            return Response::apiData($data);
        }
        // Redirect back to admin page with hash value
        return Redirect::to(URL::previous() . '#' . $payload['role'])->with('message', 'ec5_88');
    }

    /**
     * Attempt to remove a project role from a user
     * If the current user is creator/manager,
     * they cannot remove their own role
     *
     * @param RuleProjectRole $ruleProjectRole
     * @param RuleEmail $ruleEmail
     * @param ProjectService $projectService
     * @return JsonResponse|RedirectResponse
     */
    public function removeUserRole(RuleProjectRole $ruleProjectRole, RuleEmail $ruleEmail, ProjectService $projectService)
    {
        // Only managers and up have access
        if (!$this->requestedProjectRole()->canManageUsers()) {
            // If ajax, return error json
            if (request()->ajax()) {
                return Response::apiErrorCode(404, ['manage-users' => ['ec5_91']]);
            }
            return Redirect::back()->withErrors(['ec5_91']);
        }

        // Retrieve post data
        $payload = request()->all();
        $ruleEmail->validate($payload);
        if ($ruleEmail->hasErrors()) {
            // If ajax, return error json
            if (request()->ajax()) {
                return Response::apiErrorCode(400, $ruleEmail->errors());
            }
            return Redirect::back()->withErrors($ruleEmail->errors());
        }

        // Retrieve the user whose role is to be removed
        $user = User::where('email', '=', $payload['email'])->first();
        if (!$user) {
            if (request()->ajax()) {
                return Response::apiErrorCode(400, ['manage-users' => ['ec5_90']]);
            }
            return Redirect::back()->withErrors(['ec5_90']);
        }
        // Get their existing role
        $userProjectRole = $projectService->getRole($user, $this->requestedProject()->getId());
        // Additional checks on the user and their existing role
        $ruleProjectRole->additionalChecks(
            $this->requestedUser(),
            $user,
            $this->requestedProjectRole()->getRole(),
            null,
            $userProjectRole->getRole()
        );
        if ($ruleProjectRole->hasErrors()) {
            if (request()->ajax()) {
                return Response::apiErrorCode(400, $ruleProjectRole->errors());
            }
            return Redirect::back()->withErrors($ruleProjectRole->errors());
        }

        try {
            // Remove the project role for this user
            ProjectRole::where('user_id', $user->id)
                ->where('project_id', $this->requestedProject()->getId())
                ->delete();
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            if (request()->ajax()) {
                return Response::apiErrorCode(400, ['manage-user' => ['ec5_104']]);
            }
            return redirect()->back()->withErrors(['manage-user' => ['ec5_104']]);
        }

        // If ajax, return success json
        if (request()->ajax()) {
            // Send http status code 200, ok!
            return Response::apiData(['message' => config('epicollect.codes.ec5_89')]);
        }
        // Redirect back to admin page
        return Redirect::back()->with('message', 'ec5_89');
    }
}
