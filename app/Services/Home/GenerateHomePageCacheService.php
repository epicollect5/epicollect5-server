<?php

namespace ec5\Services\Home;

use ec5\Libraries\Utilities\Common;
use ec5\Models\Project\Project;
use ec5\Models\System\SystemStats;
use ec5\Services\Project\ProjectLogoService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateHomePageCacheService
{
    private ProjectLogoService $logoService;

    public function __construct(ProjectLogoService $logoService)
    {
        $this->logoService = $logoService;
    }

    /**
     * Generate and cache the featured projects content with base64-encoded logos
     */
    public function generate(): bool
    {
        $cacheKey = config(
            'epicollect.setup.system.cache.homepage_cache_key',
            'homepage_cached_content'
        );
        $cacheTTLHours = config(
            'epicollect.setup.system.cache.homepage_cache_ttl_hours',
            24
        );

        try {
            // Fetch featured projects
            $allFeaturedProjects = (new Project())->featured();

            // Calculate rows layout
            $projectsFirstRow = $allFeaturedProjects->splice(0, 4);
            $projectsSecondRow = $allFeaturedProjects->splice(0, 4);

            // Fetch stats
            $dailySystemStats = new SystemStats();
            $dailySystemStats->initDailyStats();

            $users = Common::roundNumber($dailySystemStats->getUserStats()->total, 0);
            $projectStats = $dailySystemStats->getProjectStats()->total;
            $publicProjects = $projectStats->public->hidden + $projectStats->public->listed;
            $privateProjects = $projectStats->private->hidden + $projectStats->private->listed;
            $totalProjects = Common::roundNumber($publicProjects + $privateProjects, 0);
            $entriesStats = $dailySystemStats->getEntriesStats()->total;
            $branchEntriesStats = $dailySystemStats->getBranchEntriesStats()->total;
            $totalEntries = $entriesStats->public + $entriesStats->private;
            $totalBranchEntries = $branchEntriesStats->public + $branchEntriesStats->private;
            $totalAllEntries = Common::roundNumber($totalEntries + $totalBranchEntries, 0);

            $logoService = $this->logoService;

            // Process logos for first row
            foreach ($projectsFirstRow as $project) {
                $project->logo_base64 = $this->getProjectLogoBase64($project, $logoService);
            }

            // Process logos for second row
            foreach ($projectsSecondRow as $project) {
                $project->logo_base64 = $this->getProjectLogoBase64($project, $logoService);
            }

            // Render the HTML
            $html = view('partials.home-featured-cached', [
                'projectsFirstRow' => $projectsFirstRow,
                'projectsSecondRow' => $projectsSecondRow,
                'users' => $users,
                'projects' => $totalProjects,
                'entries' => $totalAllEntries,
            ])->render();

            // Cache for configured TTL hours
            Cache::put($cacheKey, $html, now()->addHours($cacheTTLHours));

            Log::info('Home page cache generated successfully', [
                'featured_projects_count' => count($projectsFirstRow) + count($projectsSecondRow),
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('Failed to generate home page cache', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return false;
        }
    }

    /**
     * Retrieve project logo as base64 WebP data URI.
     *
     * Delegates to ProjectLogoService; falls back to a placeholder URL
     * when no logo is available or processing fails.
     */
    private function getProjectLogoBase64(object $project, ProjectLogoService $logoService): string
    {
        if ($project->access === config('epicollect.strings.project_access.private')) {
            return url('/images/ec5-placeholder-256x256.jpg');
        }

        if (empty($project->has_logo)) {
            return url('/images/ec5-placeholder-256x256.jpg');
        }

        $dimensions = config('epicollect.media.project_thumb_small');
        $logoBase64 = $logoService->generate($project->ref, $dimensions[0], $dimensions[1], 50);

        return $logoBase64 ?? url('/images/ec5-placeholder-256x256.jpg');
    }
}
