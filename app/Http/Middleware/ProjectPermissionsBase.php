<?php

namespace ec5\Http\Middleware;

use ec5\Repositories\QueryBuilder\ProjectRole\SearchRepository as ProjectRoleSearch;
use ec5\Repositories\QueryBuilder\Project\SearchRepository;

use ec5\Models\Projects\Project;
use ec5\Models\ProjectRoles\ProjectRole;

use ec5\Http\Controllers\Api\ApiResponse as ApiResponse;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Closure;
use Config;
use Auth;

abstract class ProjectPermissionsBase extends MiddlewareBase
{

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var SearchRepository
     */
    protected $search;

    /**
     * @var ProjectRoleSearch
     */
    protected $projectRoleSearch;

    /**
     * @var ApiResponse
     */
    protected $apiResponse;

    /**
     * @var
     */
    protected $error;

    /**
     * @var Project
     */
    protected $requestedProject;

    /**
     * @var
     */
    protected $requestedUser;

    /**
     * @var
     */
    protected $requestedProjectApiApplication;

    /**
     * @var ProjectRole
     */
    protected $requestedProjectRole;

    /*
    |--------------------------------------------------------------------------
    | ProjectPermissionsBase Middleware
    |--------------------------------------------------------------------------
    |
    | This middleware handles project requests.
    |
    */

    /**
     * ProjectPermissionsBase constructor.
     * @param Request $request
     * @param SearchRepository $search
     * @param ProjectRoleSearch $projectRoleSearch
     * @param ApiResponse $apiResponse
     * @param Project $requestedProject
     */
    public function __construct(Request $request, SearchRepository $search, ProjectRoleSearch $projectRoleSearch, ApiResponse $apiResponse, Project $requestedProject)
    {
        $this->search = $search;
        $this->projectRoleSearch = $projectRoleSearch;
        $this->requestedProject = $requestedProject;
        $this->request = $request;

        parent::__construct($apiResponse);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {

        // Update the request
        $this->request = $request;

        // Set the project
        $this->setRequestedProject($this->request);

        // Check the project
        if ($this->requestedProject == null) {
            $this->error = 'ec5_11';
            return $this->errorResponse($this->request, $this->error, 404);
        }

        // Set the user
        $this->setRequestedUser($this->request);

        // Set the project role
        $this->setRequestedProjectRole();

        // Check if has permission to access
        if (!$this->hasPermission()) {
            return $this->errorResponse($this->request, $this->error, 404);
        }

        $this->request->attributes->add(['requestedProject' => $this->requestedProject]);
        $this->request->attributes->add(['requestedUser' => $this->requestedUser]);
        $this->request->attributes->add(['requestedProjectRole' => $this->requestedProjectRole]);

        return $next($this->request);
    }

    /**
     * Check the given user/client has permission to access
     *
     * This function must be implemented in the child classes
     *
     * For example, the formbuilder requires a role, whether the
     * project is public or private
     * The dataviewer and app only require a role if the project is private
     *
     * @return bool
     */
    public abstract function hasPermission();

    // HELPER METHODS //

    /**
     * @param $request
     */
    private function setRequestedProject(Request $request)
    {
        $slug = $request->route()->parameter('project_slug');

        $slug = Str::slug($slug, '-');

        if ($slug !== '') {

            // Retrieve project (legacy way,  R&A fiasco)
            $project = $this->search->find($slug, $columns = array('*'));

            if ($project) {
                // Initialise the main Project model
                $this->requestedProject->init($project);
                return;
            }
        }

        // Otherwise requestedProject set as null
        $this->requestedProject = null;
    }

    /**
     * @param $request
     */
    protected function setRequestedUser(Request $request)
    {
        // Grab user from the request
        $this->requestedUser = $request->user();
    }

    /**
     *
     */
    protected function setRequestedProjectRole()
    {
        // Retrieve user role
        $this->requestedProjectRole = $this->projectRoleSearch->getRole($this->requestedUser, $this->requestedProject->getId());

        // If no role is found, but the user is an admin/super admin, add creator role
        if (
            !$this->requestedProjectRole->getRole() && $this->requestedUser &&
            ($this->requestedUser->isAdmin() || $this->requestedUser->isSuperAdmin())
        ) {
            $this->requestedProjectRole->setRole($this->requestedUser, $this->requestedProject->getId(), Config::get('ec5Permissions.projects.creator_role'));
        }
    }
}
