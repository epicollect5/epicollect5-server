<?php

namespace ec5\Http\Controllers\Web\Admin;

use ec5\Repositories\Eloquent\User\UserRepository;
use ec5\Repositories\QueryBuilder\Project\SearchRepository as ProjectSearch;
use ec5\Repositories\QueryBuilder\ProjectRole\SearchRepository as ProjectRoleSearch;
use ec5\Http\Controllers\Controller;
use Illuminate\Http\Request;
use ec5\Models\Users\User;
use Config;

class AdminController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Admin Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the server administration tasks.
    |
    */

    /**
     * @var UserRepository
     */
    protected $userRepository;

    /**
     * @var ProjectSearch
     */
    protected $projectSearch;

    /**
     * @var ProjectRoleSearch
     */
    protected $projectRoleSearch;

    /**
     * Create a new admin controller instance.
     * Restricted to admin and superadmin users
     *
     * @param UserRepository $userRepository
     * @param ProjectSearch $projectSearch
     */
    public function __construct(
        UserRepository $userRepository,
        ProjectSearch $projectSearch,
        ProjectRoleSearch $projectRoleSearch
    ) {
        $this->userRepository = $userRepository;
        $this->projectSearch = $projectSearch;
        $this->projectRoleSearch = $projectRoleSearch;
    }

    /**
     * @param Request $request
     * @param null $action
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\JsonResponse|\Illuminate\View\View
     * @throws \Throwable
     */
    public function index(Request $request, $action = null)
    {
        // The view that will be returned if an ajax call is made, ie pagination of users, projects etc
        $ajaxView = '';

        switch ($action) {
            case 'projects':
                $params = $this->projects($request);
                $action = 'projects';
                $ajaxView = 'admin.tables.projects';
                break;
            case 'import-project':
                // No further action needed
                break;
            case 'stats':
                $action = 'stats';
                break;
            default:
                $params = $this->users($request);
                $action = 'user-administration';
                $ajaxView = 'admin.tables.users';
        }

        $params['action'] = $action;

        // If ajax, return rendered html from $ajaxView
        if ($request->ajax()) {
            return response()->json(view($ajaxView, $params)->render());
        }

        // Return view with relevant params
        return view('admin.admin', $params);
    }

    /**
     * Display a list of users, paginated, against an optional search/filter query
     *
     * @param Request $request
     * @return array
     */
    private function users(Request $request)
    {
        // Get request data
        $data = $request->all();
        $perPage = Config::get('ec5Limits.users_per_page');

        $adminUser = $request->user();

        // Set search/filter/filter option defaults
        $search = !empty($data['search']) ? $data['search'] : '';
        $options['filter'] = !empty($data['filter']) ? $data['filter'] : '';
        $options['filter_option'] = !empty($data['filterOption']) ? $data['filterOption'] : '';
        $currentPage = !empty($data['page']) ? $data['page'] : 1;

        $users = $this->userRepository->paginate($perPage, $currentPage, $search, $options);
        $users->appends($options);
        $users->appends(['search' => $search]);

        return ['users' => $users, 'adminUser' => $adminUser];
    }

    /**
     * Display a list of projects, paginated, against an optional search/filter query
     *
     * @param Request $request
     * @return array
     */
    private function projects(Request $request)
    {
        $adminUser = $request->user();

        // Get request data
        $options = $request->all();
        $perPage = Config::get('ec5Limits.admin_projects_per_page');

        //get projects paginated
        $projects = $this->projectSearch->adminProjects($perPage, $options);

        // Append the creator user's User object and current user's ProjectRole object
        foreach ($projects as $project) {
            $project->user = User::where('id', '=', $project->created_by)->first();
            $project->my_role = $this->projectRoleSearch->getRole($adminUser, $project->project_id)->getRole();
        }

        $projects->appends($options);

        return ['projects' => $projects];
    }

    public function showProjects(Request $request)
    {
        $adminUser = $request->user();
        $view = 'admin.tables.projects';
        $action = 'projects';

        // Get request data
        $options = $request->all();
        $perPage = Config::get('ec5Limits.admin_projects_per_page');

        //get projects paginated
        $projects = $this->projectSearch->adminProjects($perPage, $options);

        // Append the creator user's User object and current user's ProjectRole object
        foreach ($projects as $project) {
            $project->user = User::where('id', '=', $project->created_by)->first();
            $project->my_role = $this->projectRoleSearch->getRole($adminUser, $project->project_id)->getRole();
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

    //return stats view
    public function showStats(Request $request)
    {
        $action = 'stats';

        // Return view with relevant params
        return view('admin.admin', ['action' => $action]);
    }
}
