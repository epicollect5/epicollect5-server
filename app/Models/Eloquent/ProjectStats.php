<?php

namespace ec5\Models\Eloquent;

use DB;
use ec5\Models\Projects\Project;
use Eloquent;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Log;

/**
 * @mixin Eloquent
 */
class ProjectStats extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'project_stats';
    public $timestamps = false;

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

    public function updateProjectStats($projectId): bool
    {
        try {
            DB::beginTransaction();
            $this->updateEntryCounters($projectId);
            $this->updateBranchEntryCounters($projectId);
            DB::commit();
            return true;
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed', [
                'project_id' => $projectId,
                'exception' => $e->getMessage()
            ]);
            DB::rollBack();
            return false;
        }
    }

    public function updateEntryCounters($projectId)
    {
        //find total entries per each form
        $entriesTable = config('epicollect.strings.database_tables.entries');
        $stats = DB::table($entriesTable)
            ->select(DB::raw("count(*) as total_entries, min(created_at) as first_entry_created, max(created_at) as last_entry_created, form_ref"))
            ->where('project_id', '=', $projectId)
            ->groupBy('form_ref')
            ->get();
        $statsCount = [];
        $totalCount = 0;

        //loop each form and get the overall total
        foreach ($stats as $stat) {

            $firstEntryCreated = $stat->first_entry_created;
            $lastEntryCreated = $stat->last_entry_created;

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

    public function updateBranchEntryCounters($projectId)
    {
        $branchEntriesTable = config('epicollect.strings.database_tables.branch_entries');
        $stats = DB::table($branchEntriesTable)
            ->select(DB::raw("COUNT(*) as total_entries, min(created_at) as first_entry_created, max(created_at) as last_entry_created, owner_input_ref"))
            ->where('project_id', '=', $projectId)
            ->groupBy('owner_input_ref')
            ->get();

        $statsCount = [];
        foreach ($stats as $stat) {
            $statsCount[$stat->owner_input_ref] = [
                'count' => $stat->total_entries,
                'first_entry_created' => $stat->first_entry_created,
                'last_entry_created' => $stat->last_entry_created
            ];
        }

        DB::table($this->table)
            ->where('project_id', '=', $projectId)
            ->update(['branch_counts' => json_encode($statsCount)]);
    }
}
