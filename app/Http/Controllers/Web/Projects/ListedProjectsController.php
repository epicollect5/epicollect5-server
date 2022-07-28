<?php

namespace ec5\Http\Controllers\Web\Projects;

use ec5\Http\Controllers\Controller;

use ec5\Http\Validation\Project\RuleCategories as CategoriesValidator;

use ec5\Repositories\QueryBuilder\Project\SearchRepository as Projects;

use Config;

class ListedProjectsController extends Controller
{
    /**
     * @var
     */
    protected $projects;

    /**
     * @var
     */
    protected $categoriesValidator;

    /**
     * ProjectsController constructor.
     * @param Projects $projects
     * @param CategoriesValidator $categoriesValidator
     */
    public function __construct(Projects $projects, CategoriesValidator $categoriesValidator)
    {
        $this->projects = $projects;
        $this->categoriesValidator = $categoriesValidator;
    }


    /**
     * @param string $category
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show($category = '')
    {
        $data = ['category' => $category];
        // If category is invalid, default
        $this->categoriesValidator->validate($data);
        if ($this->categoriesValidator->hasErrors()) {
            $category = Config::get('ec5Enums.search_projects_defaults.category');
        }



        $projects = $this->projects->publicAndListed($category,
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

    public function search()
    {
        return 'search page';
    }

}
