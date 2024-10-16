<?php

namespace ec5\Http\Controllers\Web\Admin;

use ec5\Http\Controllers\Controller;
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
        $data = $request->all();
        $perPage = config('epicollect.limits.users_per_page');
        $adminUser = $request->user();
        $ajaxView = 'admin.tables.users';

        // Set search/filter/filter option defaults
        $search = !empty($data['search']) ? $data['search'] : '';
        $options['filter'] = !empty($data['filter']) ? $data['filter'] : '';
        $options['filter_option'] = !empty($data['filterOption']) ? $data['filterOption'] : '';
        $currentPage = !empty($data['page']) ? $data['page'] : 1;

        $users = UserService::getAllUsers($perPage, $currentPage, $search, $options);
        $users->appends($options);
        $users->appends(['search' => $search]);

        $params = [
            'action' => 'users',
            'users' => $users,
            'adminUser' => $adminUser
        ];

        // If ajax, return rendered html from $ajaxView
        if ($request->ajax()) {
            return response()->json(view($ajaxView, $params)->render());
        }
        // Return view with relevant params
        return view('admin.admin', $params);
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
        $options = $request->all();
        $perPage = config('epicollect.limits.admin_projects_per_page');

        //get projects paginated
        $projects = $this->projectModel->admin($perPage, $options);

        // Append the creator user's User object and current user's ProjectRole object
        foreach ($projects as $project) {
            $project->user = User::where('id', '=', $project->created_by)->first();
            $project->my_role = $projectService->getRole($adminUser, $project->project_id)->getRole();
        }

        $projects->appends($options);

        $params = [
            'projects' => $projects,
            'action' => $action
        ];

        // If ajax, return rendered html from $ajaxView
        if ($request->ajax()) {
            return response()->json(view($view, $params)->render());
        }

        // Return view with relevant params
        return view('admin.admin', $params);
    }

    public function showStats()
    {
        $action = 'stats';
        // Return stats view with relevant params
        return view('admin.admin', ['action' => $action]);
    }
}
