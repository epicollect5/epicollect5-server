<?php

namespace ec5\Models\Eloquent\Counters;

use ec5\Models\Entries\EntryStructure;
use ec5\Models\Projects\Project;
use Illuminate\Database\Eloquent\Model;
use DB;

class EntryCounter extends Model
{
    protected $table = 'entries';
    //disable eloquent timestamps because we are using "uploaded_at"
    public $timestamps = false;

    /**
     * Perform any entry type specific additional stats
     *
     * @param Project $project
     * @param EntryStructure $entryStructure
     * @return bool
     */
    public function updateCounters(Project $project, EntryStructure $entryStructure): bool
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
     * @param Project $project
     * @param EntryStructure $entryStructure
     */
    public function updateEntryCounts(Project $project, EntryStructure $entryStructure)
    {
        //are there any child forms or branches?
        $projectDefinition = $project->getProjectStats()->toArray();
        $formCounts = $projectDefinition['form_counts'];
        $branches = $projectDefinition['branch_counts'];
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
            $branchCounts = $this->getEntryBranchCounts($project, $entryStructure->getProjectId(), $entryStructure->getFormRef(), $entryStructure->getEntryUuid());
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
     * @param EntryStructure $entryStructure
     */
    public function updateParentEntryCounts(EntryStructure $entryStructure)
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
    public function getFormEntryCounts($projectId, $formRef, $parentEntryUuid)
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