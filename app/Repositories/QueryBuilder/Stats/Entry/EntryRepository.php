<?php

namespace ec5\Repositories\QueryBuilder\Stats\Entry;

use ec5\Models\Projects\Project;
use ec5\Models\Entries\EntryStructure;

use Config;
use DB;
use Log;

class EntryRepository extends StatsRepository
{
    /**
     * Perform any entry type specific additional stats
     *
     * @param Project $project
     * @param EntryStructure $entryStructure
     * @return bool
     */
    public function updateAdditionalStats(Project $project, EntryStructure $entryStructure)
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
        $projectDefinition = $project->getProjectStats()->getData();
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
        DB::table($this->entryTable)
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

        \Log::error('updateParentEntryCounts called');
        $childCounts = $this->getEntryChildCounts($entryStructure->getProjectId(), $entryStructure->getFormRef(), $entryStructure->getParentUuid());

        // Update the parent entry
        DB::table($this->entryTable)
            ->where('project_id', '=', $entryStructure->getProjectId())
            ->where('uuid', '=', $entryStructure->getParentUuid())
            ->update(['child_counts' => $childCounts]);
    }
}
