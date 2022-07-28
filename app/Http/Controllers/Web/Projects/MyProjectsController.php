<?php

namespace ec5\Http\Controllers\Web\Projects;

use Illuminate\Http\Request;

use ec5\Http\Requests;
use ec5\Http\Controllers\Controller;

use ec5\Repositories\QueryBuilder\Project\SearchRepository as ProjectSearch;

use Config;

class MyProjectsController extends Controller
{
    /**
     * @var
     */
    protected $projectSearch;

    /**
     * ProjectsController constructor.
     * @param ProjectSearch $projectSearch
     */
    public function __construct(ProjectSearch $projectSearch)
    {
        $this->projectSearch = $projectSearch;
    }

    /**
     * Display a listing of my projects.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show(Request $request)
    {


        // Get request data
        $data = $request->all();
        $perPage = Config::get('ec5Limits.projects_per_page');
        // Set search/filter/filter option defaults
        $options['search'] = !empty($data['search']) ? $data['search'] : '';
        $options['filter_type'] = !empty($data['filter_type']) ? $data['filter_type'] : '';
        $options['filter_value'] = !empty($data['filter_value']) ? $data['filter_value'] : '';
        $options['page'] = !empty($data['page']) ? $data['page'] : 1;

        $projects = $this->projectSearch->myProjects($perPage, $request->user()->id, $options,
            [
                'projects.name',
                'projects.slug',
                'projects.logo_url',
                'projects.created_at',
                'projects.access',
                'projects.status',
                'projects.visibility',
                'projects.small_description',
                'projects.logo_url',
                'project_roles.role'
            ]
        );
        $projects->appends($options);
        // If ajax, return rendered html
        if ($request->ajax()) {
            return response()->json(view('projects.project_cards', ['projects' => $projects])->render());
        }
        return view('projects.my_projects',
            [
                'projects' => $projects,
                //exposing email so users know what email they are logged in with
                'email' => auth()->user()->email
            ]
        );
    }
}
