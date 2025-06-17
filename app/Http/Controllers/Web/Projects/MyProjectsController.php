<?php

namespace ec5\Http\Controllers\Web\Projects;

use ec5\Http\Controllers\Controller;
use ec5\Models\Project\Project;
use Illuminate\Http\Request;
use Throwable;

class MyProjectsController extends Controller
{
    private Project $projectModel;

    /****
     * Initializes the controller with a Project model instance.
     *
     * @param Project $projectModel The Project model to be used by the controller.
     */
    public function __construct(Project $projectModel)
    {
        $this->projectModel = $projectModel;
    }

    //Display a listing of user projects, any role

    /**
     * Displays the authenticated user's projects with optional search and filtering.
     *
     * Retrieves and paginates the user's projects based on request parameters. Returns either a rendered HTML view or a JSON response with project cards, depending on whether the request is AJAX.
     *
     * @throws Throwable
     */
    public function show(Request $request)
    {
        $data = $request->all();
        $perPage = config('epicollect.limits.projects_per_page');
        // Set search/filter/filter option defaults
        $params['search'] = !empty($data['search']) ? $data['search'] : '';
        $params['filter_type'] = !empty($data['filter_type']) ? $data['filter_type'] : '';
        $params['filter_value'] = !empty($data['filter_value']) ? $data['filter_value'] : '';
        $params['page'] = !empty($data['page']) ? $data['page'] : 1;

        $projects = $this->projectModel->myProjects($perPage, auth()->user()->id, $params);
        $projects->appends($params);
        // If ajax, return rendered html
        if ($request->ajax()) {
            return response()->json(view('projects.project_cards', ['projects' => $projects])->render());
        }
        return view(
            'projects.my_projects',
            [
                'projects' => $projects,
                //exposing email so users know what email they are logged in with
                'email' => auth()->user()->email
            ]
        );
    }
}
