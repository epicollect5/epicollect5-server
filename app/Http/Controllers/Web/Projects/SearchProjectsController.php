<?php

namespace ec5\Http\Controllers\Web\Projects;

use ec5\Http\Controllers\Controller;
use ec5\Models\Eloquent\Project;
use ec5\Http\Validation\Project\RuleSearch;
use Illuminate\Http\Request;

class SearchProjectsController extends Controller
{
    protected $projectModel;
    protected $ruleSearch;

    public function __construct(Project $projectModel, RuleSearch $ruleSearch)
    {
        $this->projectModel = $projectModel;
        $this->ruleSearch = $ruleSearch;
    }

    public function show(Request $request)
    {
        //defaults
        $params = [
            'sort_by' => 'total_entries',
            'sort_order' => 'desc',
            'name' => '',
            'page' => '1'
        ];

        //if it is an ajax request, validate parameters
        if ($request->ajax()) {
            if (!empty($request->sort_by)) {
                $params['sort_by'] = $request->sort_by;
            }
            if (!empty($request->sort_order)) {
                $params['sort_order'] = $request->sort_order;
            }
            if (!empty($request->name)) {
                $params['name'] = $request->name;
            }
            if (!empty($request->page)) {
                $params['page'] = $request->page;
            }
            //validate request parameters
            $this->ruleSearch->validate($params);
            if ($this->ruleSearch->hasErrors()) {
                //loop each error and set parameter to its default when it fails
                foreach ($this->ruleSearch->errors() as $parameter => $error) {
                    switch ($parameter) {
                        case 'name':
                            $params['name'] = '';
                            break;
                        case 'sort_by':
                            $params['sort_by'] = 'total_entries';
                            break;
                        case 'sort_order':
                            $params['sort_order'] = 'desc';
                            break;
                        case 'page':
                            $params['page'] = '1';
                            break;
                    }
                }
            }
        }

        $projects = $this->projectModel->publicAndListed(
            null,
            $params
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
