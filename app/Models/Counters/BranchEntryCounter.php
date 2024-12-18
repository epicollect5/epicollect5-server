<?php

namespace ec5\Models\Counters;

use DB;
use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;
use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;

class BranchEntryCounter extends Model
{
    /**
     * @property int $id
     * @property int $project_id
     * @property string $uuid
     * @property int $owner_entry_id
     * @property string $owner_uuid
     * @property string $owner_input_ref
     * @property string $form_ref
     * @property int|null $user_id
     * @property string $platform
     * @property string $device_id
     * @property string $created_at
     * @property string $uploaded_at
     * @property string $title
     * @property string|null $entry_data
     * @property string|null $geo_json_data
     */

    use SerializeDates;

    protected $table = 'branch_entries';
    //disable eloquent timestamps because we are using "uploaded_at"
    public $timestamps = false;

    /**
     * Perform any branch entry type specific additional stats
     *
     * @param ProjectDTO $project
     * @param EntryStructureDTO $entryStructure
     * @return bool
     */
    public function updateCounters(ProjectDTO $project, EntryStructureDTO $entryStructure): bool
    {
        $this->updateOwnerEntryBranchCounts($project, $entryStructure);
        return true;
    }

    /**
     * Update an owner entry's branch counts
     *
     * @param ProjectDTO $project
     * @param EntryStructureDTO $entryStructure
     * @return bool
     */
    public function updateOwnerEntryBranchCounts(ProjectDTO $project, EntryStructureDTO $entryStructure): bool
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
     * @param ProjectDTO $project
     * @param $projectId
     * @param $formRef
     * @param $ownerEntryUuid
     * @return array
     */
    public function getEntryBranchCounts(ProjectDTO $project, $projectId, $formRef, $ownerEntryUuid): array
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
    public function getBranchEntryCounts($projectId, $formRef, $ownerInputRef, $ownerEntryUuid): mixed
    {
        DB::table($this->table)
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

        return $counts->isEmpty() ? 0 : $counts->max('branch_entries_counts');
    }
}
