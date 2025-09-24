<?php

namespace ec5\Models\Project;

use Carbon\CarbonInterface;
use DB;
use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Log;
use Throwable;

/**
 * @property int $id
 * @property int $project_id
 * @property int $total_entries
 * @property mixed $form_counts
 * @property mixed $branch_counts
 * @property string $updated_at
 */
class ProjectStats extends Model
{
    use SerializeDates;

    protected $table = 'project_stats';
    public $timestamps = false;
    public $guarded = [];

    public function getMostRecentEntryTimestamp(): string
    {
        $formCounts = json_decode($this->form_counts, true);

        if (empty($formCounts)) {
            return '';
        }

        $timestamps = collect($formCounts)
            ->pluck('last_entry_created')
            ->reject(function ($entry) {
                return empty($entry);
            })
            ->map(function ($entry) {
                return strtotime($entry);
            });

        $mostRecentTimestamp = $timestamps->max();

        return $mostRecentTimestamp > 0 ? $mostRecentTimestamp : '';
    }


    /**
     * Function to update the project stats for entries.
     * Including counts for each form, total number of entries across all forms
     * and counts for each branch for each form.
     *
     * @param int $projectId
     * @return bool
     * @throws Throwable
     */
    public function updateProjectStats(int $projectId): bool
    {
        try {
            DB::beginTransaction();
            $this->updateEntryCounters($projectId);
            $this->updateBranchEntryCounters($projectId);
            DB::commit();
            return true;
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed', [
                'project_id' => $projectId,
                'exception' => $e->getMessage()
            ]);
            DB::rollBack();
            return false;
        }
    }

    /* Update the total entries and
       form counts
     */
    public function updateEntryCounters($projectId): void
    {
        //find total entries per each form
        $entriesTable = config('epicollect.tables.entries');
        $stats = DB::table($entriesTable)
            ->select(DB::raw("count(*) as total_entries, min(created_at) as first_entry_created, max(created_at) as last_entry_created, form_ref"))
            ->where('project_id', '=', $projectId)
            ->groupBy('form_ref')
            ->get();
        $statsCount = [];
        $totalCount = 0;

        //loop each form and get the overall total
        foreach ($stats as $stat) {

            $firstEntryCreated = Carbon::parse($stat->first_entry_created)->format('Y-m-d H:i:s');
            $lastEntryCreated = Carbon::parse($stat->last_entry_created)->format('Y-m-d H:i:s');

            $totalCount += $stat->total_entries;
            $statsCount[$stat->form_ref] = [
                'count' => $stat->total_entries,
                'first_entry_created' => $firstEntryCreated,
                'last_entry_created' => $lastEntryCreated
            ];
        }

        //update totals on project stats table
        DB::table($this->table)
            ->where('project_id', '=', $projectId)
            ->update(
                [
                    'form_counts' => json_encode($statsCount),
                    'total_entries' => $totalCount
                ]
            );
    }

    /*
     * Update the branch counts
     */
    public function updateBranchEntryCounters($projectId): void
    {
        $branchEntriesTable = config('epicollect.tables.branch_entries');
        $stats = DB::table($branchEntriesTable)
            ->select(DB::raw("COUNT(*) as total_entries, min(created_at) as first_entry_created, max(created_at) as last_entry_created, owner_input_ref"))
            ->where('project_id', '=', $projectId)
            ->groupBy('owner_input_ref')
            ->get();

        $statsCount = [];
        foreach ($stats as $stat) {

            $firstEntryCreated = Carbon::parse($stat->first_entry_created)->format('Y-m-d H:i:s');
            $lastEntryCreated = Carbon::parse($stat->last_entry_created)->format('Y-m-d H:i:s');

            $statsCount[$stat->owner_input_ref] = [
                'count' => $stat->total_entries,
                'first_entry_created' => $firstEntryCreated,
                'last_entry_created' => $lastEntryCreated
            ];
        }

        DB::table($this->table)
            ->where('project_id', '=', $projectId)
            ->update(['branch_counts' => json_encode($statsCount)]);
    }

    /**
     * Increment the media storage usage for this project.
     *
     * Adds the specified bytes and file counts for each media type.
     * Ensures individual counters and totals never go below zero.
     *
     * Note:
     * - This counter is used for quota checks only.
     * - Exact precision is not critical; if the update fails, subsequent
     *   uploads or deletions will correct it.
     * - The update is performed atomically per media type to prevent race conditions.
     *
     * @param int $photoBytes  Bytes to add for photos
     * @param int $photoFiles  Number of photo files to add
     * @param int $audioBytes  Bytes to add for audio
     * @param int $audioFiles  Number of audio files to add
     * @param int $videoBytes  Bytes to add for videos
     * @param int $videoFiles  Number of video files to add
     * @return bool Always returns true for quota-tracking purposes
     */
    public function incrementMediaStorageUsage(
        int $photoBytes,
        int $photoFiles,
        int $audioBytes,
        int $audioFiles,
        int $videoBytes,
        int $videoFiles
    ): bool {
        try {
            // Update individual media types (both bytes and file counts, clamped to 0)
            DB::table($this->table)
                ->where('id', $this->id)
                ->update([
                    'photo_bytes' => DB::raw('GREATEST(photo_bytes + ' . $photoBytes . ', 0)'),
                    'photo_files' => DB::raw('GREATEST(photo_files + ' . $photoFiles . ', 0)'),
                    'audio_bytes' => DB::raw('GREATEST(audio_bytes + ' . $audioBytes . ', 0)'),
                    'audio_files' => DB::raw('GREATEST(audio_files + ' . $audioFiles . ', 0)'),
                    'video_bytes' => DB::raw('GREATEST(video_bytes + ' . $videoBytes . ', 0)'),
                    'video_files' => DB::raw('GREATEST(video_files + ' . $videoFiles . ', 0)'),
                ]);

            // Recompute totals as sum of all media types
            DB::table($this->table)
                ->where('id', $this->id)
                ->update([
                    'total_bytes' => DB::raw('photo_bytes + audio_bytes + video_bytes'),
                    'total_files' => DB::raw('photo_files + audio_files + video_files'),
                    'total_bytes_updated_at' => now(),
                ]);

        } catch (Throwable $e) {
            Log::error(
                "Failed to increment media storage usage for project $this->project_id: " . $e->getMessage()
            );
        }

        // Always return true: these are just rough quota counters.
        return true;
    }

    /**
     * Decrement the media storage usage for this project.
     *
     * Subtracts the specified bytes and file counts for each media type.
     * Ensures individual counters and totals never go below zero.
     *
     * Note:
     * - This is a convenience wrapper around incrementMediaStorageUsage()
     * - All values are negated and passed to the increment method
     *
     * @param int $photoBytes  Bytes to subtract for photos
     * @param int $photoFiles  Number of photo files to subtract
     * @param int $audioBytes  Bytes to subtract for audio
     * @param int $audioFiles  Number of audio files to subtract
     * @param int $videoBytes  Bytes to subtract for videos
     * @param int $videoFiles  Number of video files to subtract
     * @return bool Always returns true for quota-tracking purposes
     */
    public function decrementMediaStorageUsage(
        int $photoBytes,
        int $photoFiles,
        int $audioBytes,
        int $audioFiles,
        int $videoBytes,
        int $videoFiles
    ): bool {
        return $this->incrementMediaStorageUsage(
            -$photoBytes,
            -$photoFiles,
            -$audioBytes,
            -$audioFiles,
            -$videoBytes,
            -$videoFiles
        );
    }

    /**
     * Set the media storage usage for this project to specific values.
     *
     * Sets the exact bytes and file counts for each media type.
     * Ensures individual counters never go below zero.
     *
     * Note:
     * - This counter is used for quota checks only.
     * - Use this method for resetting stats or setting absolute values.
     * - For incremental updates, use incrementMediaStorageUsage() or decrementMediaStorageUsage().
     *
     * @param int $photoBytes  Exact bytes for photos (must be >= 0)
     * @param int $photoFiles  Exact number of photo files (must be >= 0)
     * @param int $audioBytes  Exact bytes for audio (must be >= 0)
     * @param int $audioFiles  Exact number of audio files (must be >= 0)
     * @param int $videoBytes  Exact bytes for videos (must be >= 0)
     * @param int $videoFiles  Exact number of video files (must be >= 0)
     * @return bool Always returns true for quota-tracking purposes
     */
    public function setMediaStorageUsage(
        int $photoBytes,
        int $photoFiles,
        int $audioBytes,
        int $audioFiles,
        int $videoBytes,
        int $videoFiles
    ): bool {
        try {
            // Ensure all values are non-negative
            $photoBytes = max(0, $photoBytes);
            $photoFiles = max(0, $photoFiles);
            $audioBytes = max(0, $audioBytes);
            $audioFiles = max(0, $audioFiles);
            $videoBytes = max(0, $videoBytes);
            $videoFiles = max(0, $videoFiles);

            // Set exact values for all media types
            DB::table($this->table)
                ->where('id', $this->id)
                ->update([
                    'photo_bytes' => $photoBytes,
                    'photo_files' => $photoFiles,
                    'audio_bytes' => $audioBytes,
                    'audio_files' => $audioFiles,
                    'video_bytes' => $videoBytes,
                    'video_files' => $videoFiles,
                    'total_bytes' => $photoBytes + $audioBytes + $videoBytes,
                    'total_files' => $photoFiles + $audioFiles + $videoFiles,
                    'total_bytes_updated_at' => now(),
                ]);

        } catch (Throwable $e) {
            Log::error(
                "Failed to set media storage usage for project $this->project_id: " . $e->getMessage()
            );
        }

        // Always return true: these are just rough quota counters.
        return true;
    }

    public function getMediaStorageUsage(): array
    {
        $humanReadableDate = Carbon::parse($this->total_bytes_updated_at)->diffForHumans([
            'parts' => 1,   // show only 1 unit (2 min ago)
            'short' => true, // optional: "2m ago"
            'options' => CarbonInterface::JUST_NOW // automatically handle 0 seconds
        ]);
        $totalFiles = $this->photo_files + $this->audio_files + $this->video_files;

        return [
            'photo_bytes' => $this->photo_bytes,
            'photo_files' => $this->photo_files,
            'audio_bytes' => $this->audio_bytes,
            'audio_files' => $this->audio_files,
            'video_bytes' => $this->video_bytes,
            'video_files' => $this->video_files,
            'total_bytes' => $this->total_bytes,
            'total_files' => $totalFiles,
            'total_bytes_updated_at' => $this->total_bytes_updated_at,
            'total_bytes_updated_at_human_readable' => $humanReadableDate
        ];
    }
}
