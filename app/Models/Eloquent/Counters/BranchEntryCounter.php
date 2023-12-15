<?php

namespace ec5\Models\Eloquent\Counters;

use ec5\Models\Entries\EntryStructure;
use ec5\Models\Projects\Project;
use Illuminate\Database\Eloquent\Model;
use DB;
use ec5\Traits\Eloquent\Entries;
use Illuminate\Database\Query\Builder;

class BranchEntryCounter extends Model
{
    protected $table = 'branch_entries';
    //disable eloquent timestamps because we are using "uploaded_at"
    public $timestamps = false;

    /**
     * Perform any branch entry type specific additional stats
     *
     * @param Project $project
     * @param EntryStructure $entryStructure
     * @return bool
     */
    public function updateCounters(Project $project, EntryStructure $entryStructure): bool
    {
        $this->updateOwnerEntryBranchCounts($project, $entryStructure);
        return true;
    }

    /**
     * Update an owner entry's branch counts
     *
     * @param Project $project
     * @param EntryStructure $entryStructure
     * @return bool
     */
    public function updateOwnerEntryBranchCounts(Project $project, EntryStructure $entryStructure): bool
    {
        $branchCounts = $this->getEntryBranchCounts($project, $entryStructure->getProjectId(), $entryStructure->getFormRef(), $entryStructure->getOwnerUuid());

        // Update the owner entry
        $entriesTable = config('epicollect.tables.entries');
        DB::table($entriesTable)
            ->where('project_id', '=', $entryStructure->getProjectId())
            ->where('uuid', '=', $entryStructure->getOwnerUuid())
            ->update(['branch_counts' => json_encode($branchCounts)]);

        return true;
    }

    /**
     * Get all branch counts for each branch question in an entry, given the owner entry uuid
     *
     * @param Project $project
     * @param $projectId
     * @param $formRef
     * @param $ownerEntryUuid
     * @return array
     */
    public function getEntryBranchCounts(Project $project, $projectId, $formRef, $ownerEntryUuid)
    {
        $projectExtra = $project->getProjectExtra();

        // Set count defaults
        $branchCounts = [];

        // Get array of branches from project structure for this form
        $branches = $projectExtra->getBranches($formRef);
        // Loop the branches for this form (if any)
        foreach ($branches as $ref => $value) {
            $branchCounts[$ref] = 0;
        }

        $stats = DB::table($this->table)
            ->select(DB::raw("COUNT(*) as branch_entry_count, owner_uuid, owner_input_ref"))
            ->where('project_id', '=', $projectId)
            ->where('owner_uuid', '=', $ownerEntryUuid)
            ->where('form_ref', '=', $formRef)
            ->groupBy('owner_uuid')
            ->groupBy('owner_input_ref')
            ->get();

        foreach ($stats as $stat) {
            $branchCounts[$stat->owner_input_ref] = $stat->branch_entry_count;
        }

        return $branchCounts;
    }

    /**
     * Get entry counts for a branch, related to the owner entry
     *
     * @param $projectId
     * @param $formRef
     * @param $ownerInputRef
     * @param $ownerEntryUuid
     * @return mixed
     */
    public function getBranchEntryCounts($projectId, $formRef, $ownerInputRef, $ownerEntryUuid)
    {
        $sql = DB::table($this->table)
            ->select(DB::raw("COUNT(*) as branch_entries_count"))
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $formRef)
            ->where('owner_input_ref', '=', $ownerInputRef)
            ->where('owner_uuid', '=', $ownerEntryUuid)
            ->toSql();


        $stats = DB::table($this->table)
            ->select(DB::raw("COUNT(*) as branch_entries_count"))
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $formRef)
            ->where('owner_input_ref', '=', $ownerInputRef)
            ->where('owner_uuid', '=', $ownerEntryUuid)
            ->first();

        return $stats->branch_entries_count;
    }

    /**
     * @param $projectId
     * @param $formRef
     * @return int
     */
    public function getMaxCountBranch($projectId, $formRef): int
    {
        $counts = DB::table($this->table)
            ->select(DB::raw("COUNT(*) as branch_entries_counts"))
            ->where('project_id', '=', $projectId)
            ->where('owner_input_ref', '=', $formRef)
            ->groupBy('owner_uuid')
            ->get();

        return $counts->max('branch_entries_counts');
    }
}