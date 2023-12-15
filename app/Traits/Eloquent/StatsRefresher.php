<?php

namespace ec5\Traits\Eloquent;

use ec5\Models\Eloquent\ProjectStats;
use ec5\Repositories\QueryBuilder\Project\SearchRepository;

trait StatsRefresher
{
    public function refreshProjectStats($requestedProject)
    {
        $searchProjectLegacy = new SearchRepository();
        $projectStats = new ProjectStats();
        $projectStats->updateProjectStats($requestedProject->getId());
        // Retrieve the project with updated stats (legacy way, R&A fiasco)
        $project = $searchProjectLegacy->find($requestedProject->slug);
        if ($project) {
            // Refresh the main Project model
            $requestedProject->init($project);
        }
    }
}
