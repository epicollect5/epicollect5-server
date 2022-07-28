<?php

namespace ec5\Repositories\QueryBuilder\Stats\Entry;

use ec5\Models\Projects\Project;
use ec5\Models\Entries\EntryStructure;

use DB;

class BranchEntryRepository extends StatsRepository
{

    /**
     * Perform any branch entry type specific additional stats
     *
     * @param Project $project
     * @param EntryStructure $entryStructure
     * @return bool
     */
    public function updateAdditionalStats(Project $project, EntryStructure $entryStructure)
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
    public function updateOwnerEntryBranchCounts(Project $project, EntryStructure $entryStructure)
    {

        $branchCounts = $this->getEntryBranchCounts($project, $entryStructure->getProjectId(), $entryStructure->getFormRef(), $entryStructure->getOwnerUuid());

        // Update the owner entry
        DB::table($this->entryTable)
            ->where('project_id', '=', $entryStructure->getProjectId())
            ->where('uuid', '=', $entryStructure->getOwnerUuid())
            ->update(['branch_counts' => json_encode($branchCounts)]);
    }
}
