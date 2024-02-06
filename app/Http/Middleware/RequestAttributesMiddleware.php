<?php

namespace ec5\Http\Middleware;

use Closure;
use ec5\DTO\ProjectDTO;
use ec5\DTO\ProjectRoleDTO;
use ec5\Models\Project\Project;
use ec5\Services\ProjectService;
use ec5\Traits\Middleware\MiddlewareTools;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use stdClass;
use View;

abstract class RequestAttributesMiddleware
{
    use MiddlewareTools;

    protected $request;
    protected $search;
    protected $error;
    protected $requestedProject;
    protected $requestedUser;
    /**
     * @var ProjectRoleDTO
     */
    protected $requestedProjectRole;

    /*
    |--------------------------------------------------------------------------
    | RequestAttributesMiddleware Middleware
    |--------------------------------------------------------------------------
    |
    | This middleware handles project requests.
    |
    */

    /**
     * ProjectPermissionsBase constructor.
     * @param Request $request
     * @param ProjectDTO $requestedProject
     */
    public function __construct(Request $request, ProjectDTO $requestedProject)
    {
        $this->requestedProject = $requestedProject;
        $this->request = $request;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Update the request
        $this->request = $request;

        // Set the project
        $slug = $request->route()->parameter('project_slug');
        $slug = Str::slug($slug, '-');
        if (empty($slug)) {
            return $this->middlewareErrorResponse($this->request, 'ec5_21', 404);
        }
        $this->setRequestedProject($slug);

        // Check the project exists
        if ($this->requestedProject->getId() === null) {
            return $this->middlewareErrorResponse($this->request, 'ec5_11', 404);
        }

        // Set the user
        $this->setRequestedUser($this->request);

        // Set the project role
        $this->setRequestedProjectRole();

        // Check if user has permission to access
        if (!$this->hasPermission()) {
            return $this->middlewareErrorResponse($this->request, $this->error, 404);
        }

        $this->request->attributes->add(['requestedProject' => $this->requestedProject]);
        $this->request->attributes->add(['requestedUser' => $this->requestedUser]);
        $this->request->attributes->add(['requestedProjectRole' => $this->requestedProjectRole]);

        //make attributes available to all views
        $requestAttributes = new stdClass();
        $requestAttributes->requestedProjectRole = $this->requestedProjectRole;
        $requestAttributes->requestedProject = $this->requestedProject;
        $requestAttributes->requestedUser = $this->requestedUser;
        View::share('requestAttributes', $requestAttributes);

        //handle multipart uploads
        if ($request->isMethod('POST')) {
            if ($this->isMultipartRequest($request)) {
                /**
                 *  if the request is multipart,
                 *  the content will be a string not an array,
                 *  so we need to pre-parse it and override
                 *  the original request
                 */
                $this->request->merge([
                    'data' => $this->getParsedJsonInMultipart($request)
                ]);
            }
        }

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

    private function setRequestedProject($slug)
    {
        $project = Project::findBySlug($slug);
        if ($project) {
            // Initialise the main Project model
            $this->requestedProject->init($project);
        }
    }

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
        $projectService = new ProjectService();
        // Retrieve user role
        $this->requestedProjectRole = $projectService->getRole($this->requestedUser, $this->requestedProject->getId());
        // If no role is found, but the user is an admin/super admin, add the creator role
        if (
            !$this->requestedProjectRole->getRole() && $this->requestedUser &&
            ($this->requestedUser->isAdmin() || $this->requestedUser->isSuperAdmin())
        ) {
            $this->requestedProjectRole->setRole($this->requestedUser, $this->requestedProject->getId(), config('epicollect.permissions.projects.creator_role'));
        }
    }

    private function isMultipartRequest(Request $request): bool
    {
        return strpos($request->header('Content-Type'), 'multipart/form-data') !== false;
    }

    private function getParsedJsonInMultipart(Request $request)
    {
        $content = $request->get('data');
        if (!empty($content)) {
            $decodedContent = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decodedContent;
            }
        }
        return '';
    }
}
