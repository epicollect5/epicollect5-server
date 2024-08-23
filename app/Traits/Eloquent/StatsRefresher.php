<?php

namespace ec5\Traits\Eloquent;

use ec5\DTO\ProjectDTO;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStats;

trait StatsRefresher
{
    public function refreshProjectStats(ProjectDTO $requestedProject): void
    {
        $projectStats = new ProjectStats();
        $projectStats->updateProjectStats($requestedProject->getId());
        $project = Project::findBySlug($requestedProject->slug);
        if ($project) {
            // Refresh the main Project model
            $requestedProject->initAllDTOs($project);
        }
    }
}
