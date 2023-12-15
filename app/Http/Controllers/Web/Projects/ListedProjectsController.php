<?php

namespace ec5\Http\Controllers\Web\Projects;

use ec5\Http\Controllers\Controller;
use ec5\Models\Eloquent\Project;
use ec5\Http\Validation\Project\RuleCategories;
use Config;

class ListedProjectsController extends Controller
{
    protected $projectModel;
    protected $ruleCategories;

    public function __construct(Project $projectModel, RuleCategories $ruleCategories)
    {
        $this->projectModel = $projectModel;
        $this->ruleCategories = $ruleCategories;
    }

    public function show($category = '')
    {
        $data = ['category' => $category];
        // If category is invalid, default
        $this->ruleCategories->validate($data);
        if ($this->ruleCategories->hasErrors()) {
            $category = config('epicollect.strings.project_categories.general');
        }

        $projects = $this->projectModel->publicAndListed($category,
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
            ]
        );

        return view('projects.projects_list',
            [
                'projects' => $projects,
                'selectedCategory' => $category
            ]
        );
    }
}
