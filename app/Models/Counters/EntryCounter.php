<?php

namespace ec5\Models\Counters;

use DB;
use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;
use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;

class EntryCounter extends Model
{
    use SerializeDates;

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
     */

    protected $table = 'entries';
    //disable eloquent timestamps because we are using "uploaded_at"
    public $timestamps = false;

    /**
     * Perform any entry type specific additional stats
     *
     * @param ProjectDTO $project
     * @param EntryStructureDTO $entryStructure
     * @return bool
     */
    public function updateCounters(ProjectDTO $project, EntryStructureDTO $entryStructure): bool
    {
        // Check if this entry has a parent
        $hasParent = !empty($entryStructure->getParentUuid());

        $this->updateEntryCounts($project, $entryStructure);

        if ($hasParent) {
            $this->updateParentEntryCounts($entryStructure);
        }
        return true;
    }

    /**
     * Update an entry's child and branch counts
     *
     * @param ProjectDTO $project
     * @param EntryStructureDTO $entryStructure
     */
    public function updateEntryCounts(ProjectDTO $project, EntryStructureDTO $entryStructure): void
    {
        //are there any child forms or branches?
        $formCounts = $project->getProjectStats()->form_counts;
        $branches = $project->getProjectStats()->branch_counts;
        $childCounts = 0;
        $branchCounts = 0;

        //are there any child forms? 1 is the minimum (parent) so look for > 1
        if (!empty($formCounts)) {
            if (count($formCounts) > 1) {
                //grab child counts from DB
                $childCounts = $this->getEntryChildCounts($entryStructure->getProjectId(), $entryStructure->getFormRef(), $entryStructure->getEntryUuid());
            }
        }

        //are there any branches?
        if (!empty($branches)) {
            //grab branch counts from DB
            $branchEntryCounter = new BranchEntryCounter();
            $branchCounts = $branchEntryCounter->getEntryBranchCounts($project, $entryStructure->getProjectId(), $entryStructure->getFormRef(), $entryStructure->getEntryUuid());
        }

        // Update this entry
        DB::table($this->table)
            ->where('project_id', '=', $entryStructure->getProjectId())
            ->where('uuid', '=', $entryStructure->getEntryUuid())
            ->update(['child_counts' => $childCounts, 'branch_counts' => json_encode($branchCounts)]);
    }

    /**
     * Update an entry's parent's child counts
     *
     * @param EntryStructureDTO $entryStructure
     */
    public function updateParentEntryCounts(EntryStructureDTO $entryStructure): void
    {
        $childCounts = $this->getEntryChildCounts($entryStructure->getProjectId(), $entryStructure->getFormRef(), $entryStructure->getParentUuid());
        // Update the parent entry
        DB::table($this->table)
            ->where('project_id', '=', $entryStructure->getProjectId())
            ->where('uuid', '=', $entryStructure->getParentUuid())
            ->update(['child_counts' => $childCounts]);
    }


    public function getEntryChildCounts($projectId, $formRef, $parentEntryUuid)
    {
        $stats = DB::table($this->table)
            ->select(DB::raw("COUNT(*) as child_count"))
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $formRef)
            ->where('parent_uuid', '=', $parentEntryUuid)
            ->first();

        return $stats->child_count;
    }

    /**
     * Get entry counts for a form, optionally related to a parent
     *
     * @param $projectId
     * @param $formRef
     * @param $parentEntryUuid
     * @return mixed
     */
    public function getFormEntryCounts($projectId, $formRef, $parentEntryUuid): mixed
    {
        $stats = DB::table($this->table)
            ->select(DB::raw("COUNT(*) as entries_count"))
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $formRef)
            ->where(function ($query) use ($parentEntryUuid) {
                if ($parentEntryUuid) {
                    $query->where('parent_uuid', '=', $parentEntryUuid);
                }
            })
            ->first();

        return $stats->entries_count;
    }

    /**
     * @param $projectId
     * @param $formRef
     * @return int
     */
    public function getMaxCountForm($projectId, $formRef): int
    {
        $counts = DB::table($this->table)
            ->select(DB::raw("COUNT(*) as entries_counts"))
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $formRef)
            ->groupBy('parent_uuid')
            ->get();

        return $counts->isEmpty() ? 0 : $counts->max('entries_counts');
    }
}
