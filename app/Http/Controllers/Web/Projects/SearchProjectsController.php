<?php

namespace ec5\Http\Controllers\Web\Projects;

use ec5\Http\Controllers\Controller;
use ec5\Repositories\QueryBuilder\Project\SearchRepository as Projects;
use ec5\Http\Validation\Project\RuleSearch as ProjectsSearchValidator;
use Illuminate\Http\Request;

class SearchProjectsController extends Controller
{
    /**
     * @var
     */
    protected $projects;
    /**
     * @var
     */
    protected $ProjectsSearchValidator;

    /**
     * SearchProjectsController constructor.
     * @param Projects $projects
     * @param ProjectsSearchValidator $ProjectsSearchValidator
     */
    public function __construct(Projects $projects, ProjectsSearchValidator $ProjectsSearchValidator)
    {
        $this->projects = $projects;
        $this->ProjectsSearchValidator = $ProjectsSearchValidator;
    }

    /**
     * @param string $category
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show(Request $request)
    {
        //default
        $parameters = [
            'sort_by' => 'total_entries',
            'sort_order' => 'desc',
            'name' => '',
            'page' => '1'
        ];

        //if it is an ajax request, validate parameters
        if ($request->ajax()) {
            if (!empty($request->sort_by)) {
                $parameters['sort_by'] = $request->sort_by;
            }

            if (!empty($request->sort_order)) {
                $parameters['sort_order'] = $request->sort_order;
            }

            if (!empty($request->name)) {
                $parameters['name'] = $request->name;
            }

            if (!empty($request->page)) {
                $parameters['page'] = $request->page;
            }

            //validate request parameters
            $this->ProjectsSearchValidator->validate($parameters);
            if ($this->ProjectsSearchValidator->hasErrors()) {

                //loop each error and set parameter to its default when it fails
                foreach ($this->ProjectsSearchValidator->errors() as $parameter => $error) {
                    switch ($parameter) {
                        case 'name':
                            $parameters['name'] = '';
                            break;
                        case 'sort_by':
                            $parameters['sort_by'] = 'total_entries';
                            break;
                        case 'sort_order':
                            $parameters['sort_order'] = 'desc';
                            break;
                        case 'page':
                            $parameters['page'] = '1';
                            break;
                    }
                }
            }
        }

        $projects = $this->projects->publicAndListed(
            null,
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
                'projects.category'
            ],
            $parameters
        );

        // If ajax, return rendered html
        if ($request->ajax()) {
            return response()
                ->json(view('projects.project_search_cards', ['projects' => $projects])->render());
        } else {
            //return view if request is a web request
            return view(
                'projects.projects_search',
                [
                    'projects' => $projects,
                    'selectedCategory' => 'search'
                ]
            );
        }
    }
}
