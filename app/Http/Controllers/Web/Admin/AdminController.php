<?php

namespace ec5\Http\Controllers\Web\Admin;

use Auth;
use ec5\Http\Controllers\Controller;
use ec5\Libraries\Utilities\Common;
use ec5\Models\Project\Project;
use ec5\Services\User\UserService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Log;
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
        $ajaxView = 'admin.tables.users';
        // Set search/filter/filter option defaults
        $search = !empty($params['search']) ? $params['search'] : '';

        $filters['server_role'] = !empty($params['server_role']) ? $params['server_role'] : '';
        $filters['state'] = !empty($params['state']) ? $params['state'] : '';

        $users = UserService::getAllUsers($search, $filters);
        $users->appends($filters);
        $users->appends(['search' => $search]);

        $payload = [
            'action' => 'users',
            'users' => $users
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
        try {
            $CGPSVersion = Common::getCGPSEpicollectVersion();
        } catch (GuzzleException $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            $CGPSVersion = 'n/a';
        }
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
    public function showProjects(Request $request)
    {
        $view = 'admin.tables.projects';
        $action = 'projects';

        $params = $request->all();

        //get projects paginated
        $projects = $this->projectModel->admin($params);
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
