<?php

namespace ec5\Repositories\QueryBuilder\Stats\Entry;

use ec5\Models\Projects\Project;
use ec5\Models\Entries\EntryStructure;

use Config;
use DB;
use ec5\Repositories\QueryBuilder\Base;
use Illuminate\Support\Str;
use Log;

class StatsRepository extends Base
{

    /**
     * @var string
     */
    protected $entryTable;

    /**
     * @var string
     */
    protected $branchEntryTable;

    /**
     * @var string
     */
    protected $projectStatsTable;

    /**
     * EntryStatsRepository constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->entryTable = Config::get('ec5Tables.entries');
        $this->branchEntryTable = Config::get('ec5Tables.branch_entries');
        $this->projectStatsTable = Config::get('ec5Tables.project_stats');
    }

    /**
     * Function to update the project stats for entries.
     * Including: counts for each form, total number of entries across all forms
     * and counts for each branch for each form.
     *
     * @param Project $project
     * @return bool
     */
    public function updateProjectEntryStats(Project $project)
    {
        $this->startTransaction();

        try {
            $this->updateEntryStats($project);
        } catch (\Exception $e) {
            Log::error('Stats update unsuccessful', [
                'project_id' => $project->getId(),
                'Exception' => json_encode($e)
            ]);
            $this->doRollBack();
            $this->errors = ['entries_deletion' => ['ec5_336']];
            return false;
        }

        try {
            $this->updateBranchEntryStats($project);
        } catch (\Exception $e) {
            Log::error('Stats update unsuccessful', [
                'project_id' => $project->getId(),
                'Exception' => json_encode($e)
            ]);
            $this->doRollBack();
            $this->errors = ['entries_deletion' => ['ec5_336']];
            return false;
        }

        // All good
        $this->doCommit();

        return true;
    }

    /**
     * @param Project $project
     * @param EntryStructure $entryStructure
     * @return bool
     */
    public function updateAdditionalStats(Project $project, EntryStructure $entryStructure)
    {
        //
    }

    /**
     * @param Project $project
     */
    public function updateEntryStats(Project $project)
    {
        //find total entries per each form
        $stats = DB::table($this->entryTable)
            ->select(DB::raw("count(*) as total_entries, min(created_at) as first_entry_created, max(created_at) as last_entry_created, form_ref"))
            ->where('project_id', '=', $project->getId())
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
        DB::table($this->projectStatsTable)
            ->where('project_id', '=', $project->getId())
            ->update(['form_counts' => json_encode($statsCount), 'total_entries' => $totalCount]);
    }

    /**
     * @param Project $project
     */
    public function updateBranchEntryStats(Project $project)
    {
        $stats = DB::table($this->branchEntryTable)
            ->select(DB::raw("COUNT(*) as total_entries, min(created_at) as first_entry_created, max(created_at) as last_entry_created, owner_input_ref"))
            ->where('project_id', '=', $project->getId())
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

        DB::table($this->projectStatsTable)
            ->where('project_id', '=', $project->getId())
            ->update(['branch_counts' => json_encode($statsCount)]);
    }

    /**
     * Get child counts for a parent
     *
     * @param $projectId
     * @param $formRef
     * @param $parentEntryUuid
     * @return mixed
     */
    public function getEntryChildCounts($projectId, $formRef, $parentEntryUuid)
    {

        $stats = DB::table($this->entryTable)
            ->select(DB::raw("COUNT(*) as child_count"))
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $formRef)
            ->where('parent_uuid', '=', $parentEntryUuid)
            ->first();

        return $stats->child_count;
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

        $stats = DB::table($this->branchEntryTable)
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
     * Get entry counts for a form, optionally related to a parent
     *
     * @param $projectId
     * @param $formRef
     * @param $parentEntryUuid
     * @return mixed
     */
    public function getFormEntryCounts($projectId, $formRef, $parentEntryUuid)
    {
        \Log::error('getFormEntryCounts called');
        $stats = DB::table($this->entryTable)
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

        // DB::enableQueryLog();
        \Log::error('getBranchEntryCounts called');
        $sql = DB::table($this->branchEntryTable)
            ->select(DB::raw("COUNT(*) as branch_entries_count"))
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $formRef)
            ->where('owner_input_ref', '=', $ownerInputRef)
            ->where('owner_uuid', '=', $ownerEntryUuid)
            ->toSql();

        Log::error($sql);


        $stats = DB::table($this->branchEntryTable)
            ->select(DB::raw("COUNT(*) as branch_entries_count"))
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $formRef)
            ->where('owner_input_ref', '=', $ownerInputRef)
            ->where('owner_uuid', '=', $ownerEntryUuid)
            ->first();


        //Log::error(DB::getQueryLog());

        return $stats->branch_entries_count;
    }

    /**
     * @param $projectId
     * @param $formRef
     * @return int
     */
    public function getMaxCountForm($projectId, $formRef)
    {
        $counts = DB::table($this->entryTable)
            ->select(DB::raw("COUNT(*) as entries_counts"))
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $formRef)
            ->groupBy('parent_uuid')
            ->get();

        return $counts->max('entries_counts');
    }

    /**
     * @param $projectId
     * @param $formRef
     * @return int
     */
    public function getMaxCountBranch($projectId, $formRef)
    {
        $counts = DB::table($this->branchEntryTable)
            ->select(DB::raw("COUNT(*) as branch_entries_counts"))
            ->where('project_id', '=', $projectId)
            ->where('owner_input_ref', '=', $formRef)
            ->groupBy('owner_uuid')
            ->get();

        return $counts->max('branch_entries_counts');
    }
}
