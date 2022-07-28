<?php

namespace ec5\Http\Controllers\Web;

use ec5\Http\Controllers\Controller;
use Illuminate\Http\Request;

use ec5\Repositories\QueryBuilder\Project\SearchRepository as Projects;
use ec5\Models\Eloquent\SystemStats;
use ec5\Libraries\Utilities\Common;
use Exception;

class HomeController extends Controller
{
    /**
     * @var
     */
    private $projects;
    private $dailySystemStats;

    /**
     * ProjectsController constructor.
     * @param Projects $projects
     */
    public function __construct(Projects $projects, SystemStats $systemStats)
    {
        $this->projects = $projects;
        $this->dailySystemStats = $systemStats;
        $this->dailySystemStats->initDailyStats();
    }

    /**
     * Show home page (available to all users)
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {

        $columns = [
            'projects.name',
            'projects.slug',
            'projects.logo_url',
            'projects.access',
            'projects.small_description',
        ];

        try {

            //get all featured projects (ordered by updated timestamp)
            $allFeaturedProjects = $this->projects->featuredProjects($columns);

            //first row with 2 projects, as we have the 2 social media columns
            $projectsFirstRow = $allFeaturedProjects->splice(0, 2);

            //second row with 4 projects
            $projectsSecondRow = $allFeaturedProjects->splice(0, 4);
        } catch (Exception $e) {
            \Log::error('Failed to get featured projects, maybe brand new instance?');
            $allFeaturedProjects = [];
            $projectsFirstRow = [];
            $projectsSecondRow = [];
        }

        try {
            //get total of users
            $users = Common::roundNumber($this->dailySystemStats->getUserStats()->total, 0);

            //get sum of all projects
            $projectStats = $this->dailySystemStats->getProjectStats()->total;
            $publicProjects = $projectStats->public->hidden + $projectStats->public->listed;
            $privateProjects = $projectStats->private->hidden + $projectStats->private->listed;
            $totalProjects = Common::roundNumber($publicProjects + $privateProjects, 0);

            //get sum of all entries
            $entriesStats = $this->dailySystemStats->getEntriesStats()->total;
            $branchEntriesStats = $this->dailySystemStats->getBranchEntriesStats()->total;
            $totalEntries = $entriesStats->public + $entriesStats->private;
            $totalBranchEntries = $branchEntriesStats->public + $branchEntriesStats->private;
            $totalAllEntries = Common::roundNumber($totalEntries +  $totalBranchEntries, 0);
        } catch (Exception $e) {
            \Log::error('Failed to get system stats, maybe brand new instance?', [
                'exception' => $e->getMessage()
            ]);
            $users = 0;
            $totalProjects = 0;
            $totalAllEntries = 0;
        }

        return view(
            'home',
            [
                'projectsFirstRow' => $projectsFirstRow,
                'projectsSecondRow' => $projectsSecondRow,
                'users' =>  $users,
                'projects' =>  $totalProjects,
                'entries' =>   $totalAllEntries
            ]
        );
    }
}
