<?php

namespace ec5\Services\Home;

use ec5\Libraries\Utilities\Common;
use ec5\Models\Project\Project;
use ec5\Models\System\SystemStats;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateHomePageCacheService
{
    private const string CACHE_KEY = 'home_page_cached_content';
    private const int CACHE_TTL_HOURS = 24;

    /**
     * Generate and cache the featured projects content with base64-encoded logos
     */
    public function generate(): bool
    {
        try {
            // Fetch featured projects
            $allFeaturedProjects = (new Project())->featured();

            // Calculate rows layout
            if ($allFeaturedProjects->count() > 7) {
                $projectsFirstRow = $allFeaturedProjects->splice(0, 4);
            } else {
                $projectsFirstRow = $allFeaturedProjects->splice(0, 3);
            }
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

            // Process logos for first row
            foreach ($projectsFirstRow as $project) {
                $project->logo_base64 = $this->getProjectLogoBase64($project);
            }

            // Process logos for second row
            foreach ($projectsSecondRow as $project) {
                $project->logo_base64 = $this->getProjectLogoBase64($project);
            }

            // Render the HTML
            $html = view('partials.home-featured-cached', [
                'projectsFirstRow' => $projectsFirstRow,
                'projectsSecondRow' => $projectsSecondRow,
                'users' => $users,
                'projects' => $totalProjects,
                'entries' => $totalAllEntries,
            ])->render();

            // Cache for 24 hours
            Cache::put(self::CACHE_KEY, $html, now()->addHours(self::CACHE_TTL_HOURS));

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
     * Retrieve project logo as base64 data URI via media endpoint
     * Falls back to placeholder URL if fetch fails
     */
    private function getProjectLogoBase64(object $project): string
    {
        try {
            // Skip private projects - use placeholder
            if ($project->access === config('epicollect.strings.project_access.private')) {
                return url('/images/ec5-placeholder-256x256.jpg');
            }

            // If no logo URL, use placeholder
            if (empty($project->logo_url)) {
                return url('/images/ec5-placeholder-256x256.jpg');
            }

            // Build media endpoint URL to fetch project_thumb logo
            $mediaUrl = url('/api/internal/media/' . $project->slug .
                '?type=photo&name=logo.jpg&format=project_thumb&v=' . strtotime($project->structure_last_updated));

            // Fetch the resized image from the media endpoint
            $response = Http::timeout(10)->get($mediaUrl);

            if (!$response->successful()) {
                Log::warning('Failed to fetch project logo, status: ' . $response->status(), [
                    'project_slug' => $project->slug,
                    'url' => $mediaUrl,
                ]);
                return url('/images/ec5-placeholder-256x256.jpg');
            }

            $imageData = $response->body();
            if (empty($imageData)) {
                return url('/images/ec5-placeholder-256x256.jpg');
            }

            $mimeType = $response->header('Content-Type') ?? 'image/jpeg';

            // Convert to base64 data URI
            $base64 = base64_encode($imageData);
            return "data:" . $mimeType . ";base64," . $base64;
        } catch (Throwable $e) {
            Log::warning('Exception while fetching project logo for base64 encoding', [
                'project_slug' => $project->slug ?? 'unknown',
                'exception' => $e->getMessage(),
            ]);
            return url('/images/ec5-placeholder-256x256.jpg');
        }
    }
}
