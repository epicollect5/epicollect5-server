<?php

namespace ec5\Http\Controllers\Web;

use ec5\Http\Controllers\Controller;
use ec5\Libraries\Utilities\Common;
use ec5\Models\Project\Project;
use ec5\Models\System\SystemStats;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;
use Log;

class HomeController extends Controller
{
    private Project $projectModel;
    private SystemStats $dailySystemStats;

    /**
     * ProjectsController constructor.
     */
    public function __construct(Project $projectModel, SystemStats $systemStats)
    {
        $this->projectModel = $projectModel;
        $this->dailySystemStats = $systemStats;
        $this->dailySystemStats->initDailyStats();
    }

    /**
     * Show home page (available to all users)
     *
     * @return Factory|View
     */
    public function index()
    {
        try {
            //get all featured projects (ordered by updated timestamp)
            $allFeaturedProjects = $this->projectModel->featured();
            //first row with 3 projects, as we have the community column
            $projectsFirstRow = $allFeaturedProjects->splice(0, 3);
            //second row with 4 projects
            $projectsSecondRow = $allFeaturedProjects->splice(0, 4);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
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
            $totalAllEntries = Common::roundNumber($totalEntries + $totalBranchEntries, 0);
        } catch (\Throwable $e) {
            Log::error('Failed to get system stats, maybe brand new instance?', ['exception' => $e->getMessage()]);
            $users = 0;
            $totalProjects = 0;
            $totalAllEntries = 0;
        }

        return view(
            'home',
            [
                'projectsFirstRow' => $projectsFirstRow,
                'projectsSecondRow' => $projectsSecondRow,
                'users' => $users,
                'projects' => $totalProjects,
                'entries' => $totalAllEntries
            ]
        );
    }
}
