<?php

namespace ec5\Traits\Eloquent;

use ec5\Libraries\Utilities\Common;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStats;
use Exception;
use File;
use Illuminate\Support\Facades\DB;
use Log;
use Storage;
use Throwable;

trait Remover
{
    public function removeProject($projectId, $projectSlug): bool
    {
        try {
            $project = Project::where('id', $projectId)
                ->where('slug', $projectSlug)
                ->first();
            $project->delete();
            return true;
        } catch (Throwable $e) {
            Log::error('Error removeProject()', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    public function removeEntries($projectId, $projectRef): bool
    {
        try {
            foreach (Entry::where('project_id', $projectId)->take(10000)->cursor() as $row) {
                // Delete the row
                $row->delete();
            }

            //remove all the entries media folders
            $drivers = config('epicollect.media.entries_deletable');
            foreach ($drivers as $driver) {
                // Get disk, path prefix and all directories for this driver
                $disk = Storage::disk($driver);
                $pathPrefix = $disk->path('');
                // Note: need to use File facade here, as Storage doesn't delete
                File::deleteDirectory($pathPrefix . $projectRef);
            }
            return true;
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', [
                'exception' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * @throws Throwable
     */
    public function removeEntriesChunk($projectId, $projectRef): bool
    {
        $initialMemoryUsage = memory_get_usage();
        $peakMemoryUsage = memory_get_peak_usage();
        $projectStats = new ProjectStats();
        try {
            DB::beginTransaction();

            //imp: branch entries are removed by FK constraint ON DELETE CASCADE
            Entry::where('project_id', $projectId)
                ->limit(config('epicollect.setup.bulk_deletion.chunk_size'))
                ->delete();
            if (!$projectStats->updateProjectStats($projectId)) {
                throw new Exception('Failed to count entries after archive');
            }

            // Check and update peak memory usage
            $peakMemoryUsage = max($peakMemoryUsage, memory_get_peak_usage());

            $finalMemoryUsage = memory_get_usage();
            $memoryUsed = $finalMemoryUsage - $initialMemoryUsage;

            $initialMemoryUsage = Common::formatBytes($initialMemoryUsage);
            $finalMemoryUsage = Common::formatBytes($finalMemoryUsage);
            $memoryUsed = Common::formatBytes($memoryUsed);
            $peakMemoryUsage = Common::formatBytes($peakMemoryUsage);

            // Log memory usage details
            Log::info("Memory Usage for Deleting Entries");
            Log::info("Initial Memory Usage: " . $initialMemoryUsage);
            Log::info("Final Memory Usage: " . $finalMemoryUsage);
            Log::info("Memory Used: " . $memoryUsed);
            Log::info("Peak Memory Usage: " . $peakMemoryUsage);

            //commit after each batch to release resources
            DB::commit();
            // Pause for a few seconds to avoid overloading/locking the database
            sleep(3);

            //if we have 0 entries left, delete all media files
            $totalEntries = ProjectStats::where('project_id', $projectId)->value('total_entries');
            if ($totalEntries === 0) {
                //remove all the entries media folders
                $drivers = config('epicollect.media.entries_deletable');
                foreach ($drivers as $driver) {
                    // Get disk, path prefix and all directories for this driver
                    $disk = Storage::disk($driver);
                    $pathPrefix = $disk->path('');
                    // Note: need to use File facade here, as Storage doesn't delete
                    File::deleteDirectory($pathPrefix . $projectRef);
                }
            }

            return true;
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            DB::rollBack();
            return false;
        }
    }
}
