<?php

namespace ec5\Http\Controllers\Web\Project;

use ec5\Http\Controllers\ProjectControllerBase;
use ec5\Repositories\QueryBuilder\Project\SearchRepository as ProjectSearch;
use ec5\Models\Users\User;
use ec5\Repositories\QueryBuilder\ProjectRole\SearchRepository as ProjectRoleSearch;
use ec5\Repositories\QueryBuilder\ProjectRole\CreateRepository as ProjectRoleCreate;
use ec5\Repositories\QueryBuilder\ProjectRole\DeleteRepository as ProjectRoleDelete;
use ec5\Models\Eloquent\ProjectRole;

use ec5\Http\Validation\Project\RuleProjectRole as Validator;

use ec5\Http\Controllers\Api\ApiResponse;

use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;
use Config;

class ManageUsersController extends ProjectControllerBase
{
    protected $projectRoleSearch;

    protected $projectRoleCreate;

    protected $projectRoleDelete;

    protected $projectSearch;

    /**
     * Create a new manage project controller instance.
     *
     * @param ProjectRoleSearch $projectRoleSearch
     * @param ProjectRoleCreate $projectRoleCreate
     * @param ProjectRoleDelete $projectRoleDelete
     * @param ProjectSearch $projectSearch
     * @param Request $request
     */
    public function __construct(
        ProjectRoleSearch $projectRoleSearch,
        ProjectRoleCreate $projectRoleCreate,
        ProjectRoleDelete $projectRoleDelete,
        ProjectSearch $projectSearch,
        Request $request
    ) {

        $this->projectRoleSearch = $projectRoleSearch;
        $this->projectRoleCreate = $projectRoleCreate;
        $this->projectRoleDelete = $projectRoleDelete;
        $this->projectSearch = $projectSearch;

        parent::__construct($request);
    }

    /**
     * @param Request $request
     */
    public function index(Request $request)
    {
        // Only managers (and creators) have access
        if (!$this->requestedProjectRole->isManager()) {
            return Redirect::back()->withErrors(['ec5_91']);
        }

        // Get request data
        $input = $request->all();

        // Set per page limit
        $perPage = Config::get('ec5Limits.users_per_page');

        // Set search/roles/current page option defaults
        $search = !empty($input['search']) ? $input['search'] : '';
        $options = [];
        $options['roles'] = [];
        $currentPage = 1;

        // Set up the roles and current page to retrieve
        if (!empty($input['page-manager'])) {
            $options['roles'] = ['manager'];
            $currentPage = $input['page-manager'];
        } else {
            if (!empty($input['page-curator'])) {
                $options['roles'] = ['curator'];
                $currentPage = $input['page-curator'];
            } else {
                if (!empty($input['page-collector'])) {
                    $options['roles'] = ['collector'];
                    $currentPage = $input['page-collector'];
                } else {
                    if (!empty($input['page-viewer'])) {
                        $options['roles'] = ['viewer'];
                        $currentPage = $input['page-viewer'];
                    } else {
                        // Otherwise set up all user roles
                        $options['roles'] = array_keys(Config::get('ec5Permissions.projects.roles'));
                    }
                }
            }
        }

        // Set project id in options
        $options['project_id'] = $this->requestedProject->getId();

        // Get paginated project users, based on per page, specified roles, current page and search term
        $users = $this->projectRoleSearch->paginate($perPage, $currentPage, $search, $options);

        // dd($this->projectRoleSearch->users($this->requestedProject->getId()));
        //CREATOR role can transfer ownership
        $canTransferOwnership = $this->requestedProjectRole->isCreator();

        //superadmin can transfer ownership
        if ($this->requestedProjectRole->getUser()->isSuperAdmin()) {
            $canTransferOwnership = true;
        }

        //admin can transfer ownership
        if ($this->requestedProjectRole->getUser()->isAdmin()) {
            $canTransferOwnership = true;
        }

        // If ajax, return rendered html
        if ($request->ajax()) {

            // For ajax we only want to return one rendered view
            // and one set of users for the specified role
            $projectUsers = $users[$options['roles'][0]];

            return response()->json(view(
                'project.project_users',
                [
                    'project' => $this->requestedProject,
                    'projectUsers' => $projectUsers,
                    'requestedProjectRole' => $this->requestedProjectRole,
                    'canTransferOwnership' => $canTransferOwnership,
                    'key' => $options['roles'][0]
                ]
            )
                ->render());
        }

        // Return only valid 'provider' auth methods ie not 'local'
        $authMethods = Config::get('auth.auth_methods');
        $invalidAuthMethod = array_search('local', Config::get('auth.auth_methods'));
        if ($invalidAuthMethod !== false) {
            unset($authMethods[$invalidAuthMethod]);
        }


        $projectRole = new ProjectRole();
        $countByRole = $projectRole->getCountByRole($this->requestedProject->getId());
        $countOverall = $projectRole->getCountOverlall($this->requestedProject->getId());


        return view(
            'project.project_details',
            [
                'includeTemplate' => 'manage-users',
                'project' => $this->requestedProject,
                'users' => $users,
                'countOverall' =>  $countOverall->total,
                'countByRole' => $countByRole,
                'requestedProjectRole' => $this->requestedProjectRole,
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
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @param Validator $validator
     */
    public function addUserRole(Request $request, ApiResponse $apiResponse, Validator $validator)
    {
        $requestedUser = $request->attributes->get('requestedUser');

        // Only creators and managers have access
        if (!$this->requestedProjectRole->isManager()) {
            // If ajax, return error json
            if ($request->ajax()) {
                return $apiResponse->errorResponse(404, ['manage-users' => ['ec5_91']]);
            }
            return Redirect::back()->withErrors(['ec5_91']);
        }

        // Retrieve post data
        $input = $request->all();


        // Validate the input
        $validator->validate($input);
        if ($validator->hasErrors()) {
            // If ajax, return error json
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, $validator->errors());
            }
            return Redirect::back()->withErrors($validator->errors());
        }

        // Retrieve the user whose role is to be added
        $user = User::where('email', '=', $input['email'])->first();
        if (!$user) {
            // If no user, add a placeholder user to the system
            //other fields will be filled in when the user logs in
            $user = new User();
            $user->email = $input['email'];
            $user->save();
        }
        // Attempt to get their existing role, if they have one
        $userProjectRole = $this->projectRoleSearch->getRole($user, $this->requestedProject->getId());

        // Additional checks on the user against the user performing the action,
        // using the new role passed in and user's existing role, if available
        $validator->additionalChecks(
            $requestedUser,
            $user,
            $this->requestedProjectRole->getRole(),
            $input['role'],
            $userProjectRole->getRole()
        );
        if ($validator->hasErrors()) {
            // If ajax, return error json
            if ($request->ajax()) {

                //managers cannot add/switch other manager(s)
                if ($this->requestedProjectRole->getRole() === 'manager' && $userProjectRole->getRole() === 'manager') {
                    return $apiResponse->errorResponse(400, [
                        'manage-users' => ['ec5_344']
                    ]);
                }

                return $apiResponse->errorResponse(400, $validator->errors());
            }
            return Redirect::back()->withErrors($validator->errors());
        }

        // Create the project role for this user
        $this->projectRoleCreate->create($user->id, $this->requestedProject->getId(), $input['role']);

        // If ajax, return success json
        if ($request->ajax()) {
            // Send http status code 200, ok!
            $apiResponse->setData(['message' => trans('status_codes.ec5_88')]);
            return $apiResponse->toJsonResponse(200);
        }

        // Redirect back to admin page with hash value
        return Redirect::to(URL::previous() . '#' . $input['role'])->with('message', 'ec5_88');
    }

    /**
     * Attempt to remove a project role from a user
     * If the current user is creator/manager,
     * they cannot remove their own role
     *
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @param Validator $validator
     */
    public function removeUserRole(Request $request, ApiResponse $apiResponse, Validator $validator)
    {
        $requestedUser = $request->attributes->get('requestedUser');

        // Only managers and up have access
        if (!$this->requestedProjectRole->isManager()) {

            // If ajax, return error json
            if ($request->ajax()) {
                return $apiResponse->errorResponse(404, ['manage-users' => ['ec5_91']]);
            }
            return Redirect::back()->withErrors(['ec5_91']);
        }

        // Retrieve post data
        $input = $request->all();

        // Retrieve the user whose role is to be removed
        $user = User::where('email', '=', $input['email'])->first();
        if (!$user) {
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, ['manage-users' => ['ec5_90']]);
            }
            return Redirect::back()->withErrors(['ec5_90']);
        }
        // Get their existing role
        $userProjectRole = $this->projectRoleSearch->getRole($user, $this->requestedProject->getId());
        // Additional checks on the user and their existing role
        $validator->additionalChecks(
            $requestedUser,
            $user,
            $this->requestedProjectRole->getRole(),
            null,
            $userProjectRole->getRole()
        );
        if ($validator->hasErrors()) {
            if ($request->ajax()) {
                return $apiResponse->errorResponse(400, $validator->errors());
            }
            return Redirect::back()->withErrors($validator->errors());
        }

        // Remove the project role for this user
        $this->projectRoleDelete->delete($user->id, $this->requestedProject->getId());

        // If ajax, return success json
        if ($request->ajax()) {
            // Send http status code 200, ok!
            $apiResponse->setData(['message' => trans('status_codes.ec5_89')]);
            return $apiResponse->toJsonResponse(200);
        }
        // Redirect back to admin page
        return Redirect::back()->with('message', 'ec5_89');
    }
}
