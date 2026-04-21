<?php

namespace ec5\Services\Home;

use ec5\Libraries\Utilities\Common;
use ec5\Models\Project\Project;
use ec5\Models\System\SystemStats;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

class GenerateHomePageCacheService
{
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
     * Retrieve project logo as base64 WebP data URI
     * Reads directly from storage (local or S3), resizes to project_thumb dimensions, converts to WebP
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

            // Get project thumb dimensions from config
            $dimensions = config('epicollect.media.project_thumb_small');
            $width = $dimensions[0];
            $height = $dimensions[1];

            // Check if logo exists in project disk
            $disk = Storage::disk(Common::resolveDisk('project_thumb'));
            $logoPath = $project->ref . '/logo.jpg';

            if (!$disk->exists($logoPath)) {
                Log::warning('Project logo file not found', [
                    'project_slug' => $project->slug,
                    'logo_path' => $logoPath,
                ]);
                return url('/images/ec5-placeholder-256x256.jpg');
            }

            // Read image from storage (works for both local and S3)
            $stream = $disk->readStream($logoPath);
            if (!$stream) {
                return url('/images/ec5-placeholder-256x256.jpg');
            }

            // Read image from stream
            try {
                $image = Image::read($stream);
                fclose($stream);

                // Resize to project_thumb dimensions and convert to WebP with quality 70
                $image->cover($width, $height);
                $webpData = $image->toWebp(50);
            } catch (Throwable $e) {
                Log::warning('Failed to process project logo image', [
                    'project_slug' => $project->slug,
                    'exception' => $e->getMessage(),
                ]);
                return url('/images/ec5-placeholder-256x256.jpg');
            } finally {
                // Ensure stream is closed if it wasn't already
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            // Convert to base64 data URI
            $base64 = base64_encode((string)$webpData);
            return 'data:image/webp;base64,' . $base64;
        } catch (Throwable $e) {
            Log::warning('Exception while processing project logo for base64 encoding', [
                'project_slug' => $project->slug ?? 'unknown',
                'exception' => $e->getMessage(),
            ]);
            return url('/images/ec5-placeholder-256x256.jpg');
        }
    }
}
