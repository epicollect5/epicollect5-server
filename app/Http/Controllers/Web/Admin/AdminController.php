<?php

namespace ec5\Http\Controllers\Web\Admin;

use Auth;
use ec5\Http\Controllers\Controller;
use ec5\Libraries\Utilities\Common;
use ec5\Models\Project\Project;
use ec5\Models\User\User;
use ec5\Services\Project\ProjectService;
use ec5\Services\User\UserService;
use Illuminate\Http\Request;
use Throwable;

class AdminController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Admin Controller
    |--------------------------------------------------------------------------
    */
    protected Project $projectModel;

    /**
     * Create a new admin controller instance.
     * Restricted to admin and superadmin users
     */
    public function __construct(
        Project $projectModel
    ) {
        $this->projectModel = $projectModel;
    }

    /**
     * Display a list of users, paginated, against an optional search/filter query
     * @throws Throwable
     */
    public function showUsers(Request $request)
    {
        // Get request data
        $params = $request->all();
        $perPage = config('epicollect.limits.users_per_page');
        $adminUser = $request->user();
        $ajaxView = 'admin.tables.users';

        // Set search/filter/filter option defaults
        $search = !empty($params['search']) ? $params['search'] : '';

        $filters['server_role'] = !empty($params['server_role']) ? $params['server_role'] : '';
        $filters['state'] = !empty($params['state']) ? $params['state'] : '';

        $currentPage = !empty($params['page']) ? $params['page'] : 1;

        $users = UserService::getAllUsers($perPage, $currentPage, $search, $filters);
        $users->appends($filters);
        $users->appends(['search' => $search]);

        $payload = [
            'action' => 'users',
            'users' => $users,
            'adminUser' => $adminUser
        ];

        // If ajax, return rendered html from $ajaxView
        if ($request->ajax()) {
            return response()->json(view($ajaxView, $payload)->render());
        }
        // Return view with relevant params
        return view('admin.admin', $payload);
    }

    public function showSettings()
    {
        // Get request data
        $CGPSVersion = Common::getCGPSEpicollectVersion();
        $currentVersion = config('epicollect.setup.system.version');

        $payload = [
            'action' => 'settings',
            'CGPSVersion' => $CGPSVersion,
            'currentVersion' => $currentVersion,
            'update' => version_compare($currentVersion, $CGPSVersion, '<'),
            'systemEmail' => config('epicollect.setup.system.email')
        ];

        // Return view with relevant params
        return view('admin.admin', $payload);
    }

    public function showPHPInfo()
    {
        if (!Auth::user()->isSuperAdmin()) {
            redirect()->back();
        }
        // Check if phpinfo is enabled
        if (!config('epicollect.setup.phpinfo.enabled')) {
            return response('Phpinfo output must be enabled.', 403);
        }

        return phpinfo();
    }

    public function updateSettings(Request $request)
    {

        //todo: validate with rule admin settings

        //todo:update settings
    }


    /**
     * @throws Throwable
     */
    public function showProjects(Request $request, ProjectService $projectService)
    {
        $adminUser = $request->user();
        $view = 'admin.tables.projects';
        $action = 'projects';

        // Get request data
        $params = $request->all();
        $perPage = config('epicollect.limits.admin_projects_per_page');

        //get projects paginated
        $projects = $this->projectModel->admin($perPage, $params);

        // Append the creator user's User object and current user's ProjectRole object
        foreach ($projects as $project) {
            $project->user = User::where('id', '=', $project->created_by)->first();
            $project->my_role = $projectService->getRole($adminUser, $project->project_id)->getRole();
        }

        $projects->appends($params);

        $payload = [
            'projects' => $projects,
            'action' => $action
        ];

        // If ajax, return rendered html from $ajaxView
        if ($request->ajax()) {
            return response()->json(view($view, $payload)->render());
        }

        // Return view with relevant params
        return view('admin.admin', $payload);
    }

    public function showStats()
    {
        $action = 'stats';
        // Return stats view with relevant params
        return view('admin.admin', ['action' => $action]);
    }
}
