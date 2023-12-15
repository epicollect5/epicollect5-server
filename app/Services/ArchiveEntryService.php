<?php

namespace ec5\Services;

use DB;
use ec5\Models\Eloquent\BranchEntry;
use ec5\Models\Eloquent\Counters\BranchEntryCounter;
use ec5\Models\Eloquent\Counters\EntryCounter;
use ec5\Models\Eloquent\Entry;
use ec5\Models\Eloquent\ProjectStats;
use ec5\Models\Projects\Project;
use Exception;
use Log;

class ArchiveEntryService
{
    public function archiveHierarchyEntry(Project $project, $entryStructure): bool
    {
        $entryUuid = $entryStructure->getEntryUuid();
        $table = config('epicollect.tables.entries');
        try {
            DB::beginTransaction();
            $entryModel = new Entry();
            // 1. Gather all child entries uuids related to this entry (including the original entry_uuid)
            $entryUuids = $entryModel->getChildEntriesUuids($project->getId(), [$entryUuid], $entryUuid);
            // 2. Copy the entries to the archive table
            if (!$this->copyEntries(
                $project->getId(),
                $entryUuids,
                $table
            )) {
                throw new Exception('Cannot copy hierarchy entries');
            }

            // 3. Get the Branch entry uuids
            $branchEntryModel = new BranchEntry();
            $branchEntryUuids = $branchEntryModel->getBranchEntriesUuids($project->getId(), $entryUuids);

            // 4. Archive the branch entries
            foreach ($branchEntryUuids as $branchEntryUuid) {
                // Attempt to archive
                if (!$this->archiveBranchEntry($project, $branchEntryUuid, $entryStructure, true)) {
                    throw new Exception('Cannot archive branch entries');
                }
            }

            //imp:branch entries are removed by FK - ON DELETE CASCADE

            // 5. Delete the main entries
            if (!Entry::where('project_id', $project->getId())
                ->whereIn('uuid', $entryUuids)
                ->delete()) {
                throw new Exception('Cannot delete hierarchy entries');
            }

            // 6. Update project stats
            $projectStats = new ProjectStats();
            if (!$projectStats->updateProjectStats($project->getId())) {
                throw new Exception('Cannot update project stats');
            }

            // 7. Update counters for hierarchy entries
            $entryCounter = new EntryCounter();
            if (!$entryCounter->updateCounters($project, $entryStructure)) {
                throw new Exception('Cannot update entries and branch entries counters');
            }

            // Now finally commit
            DB::commit();
            return true;
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
    }

    /**
     * @param $projectId
     * @param $formRef
     * @param $branchEntryUuid - branch entry uuid we need to archive
     * @param bool $reuseExistingTransaction
     * @return bool
     */
    public function archiveBranchEntry(Project $project, $branchEntryUuid, $entryStructure, bool $reuseExistingTransaction = false): bool
    {
        $table = config('epicollect.tables.branch_entries');
        // If we don't want to keep open the transaction for further processing
        // when deleting hierarchy entry, we have a transaction
        // when deleting only a branch, we do not
        try {
            if (!$reuseExistingTransaction) {
                DB::beginTransaction();
            }

            // Copy then delete the branch entries
            if ($this->copyEntries($project->getId(), [$branchEntryUuid], $table)) {
                BranchEntry::where('project_id', $project->getId())
                    ->where('uuid', $branchEntryUuid)
                    ->delete();
            } else {
                throw new Exception('Cannot copy branch entries');
            }

            // 6. Update project stats (only when deleting branch entry directly)
            if (!$reuseExistingTransaction) {
                $projectStats = new ProjectStats();
                if (!$projectStats->updateProjectStats($project->getId())) {
                    throw new Exception('Cannot update project stats (branch)');
                }
            }

            // 7. Update counters for branch entries
            $entryCounter = new BranchEntryCounter();
            if (!$entryCounter->updateCounters($project, $entryStructure)) {
                throw new Exception('Cannot update branch entries counters');
            }

            // If we don't want to keep open the transaction for further processing
            // Commit
            if (!$reuseExistingTransaction) {
                DB::commit();
            }
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
        return true;
    }

    private function copyEntries($projectId, $entryUuids, $table): bool
    {
        // Select all entries with an uuid in $uuids array
        DB::table($table)
            ->select('*')
            ->where('project_id', '=', $projectId)
            ->whereIn('uuid', $entryUuids)
            ->orderBy('created_at', 'DESC')
            ->chunk(100, function ($data) use ($entryUuids, $table) {
                foreach ($data as $entry) {
                    // Update or Insert into the archive table
                    if (!DB::table($table . '_archive')->updateOrInsert(['uuid' => $entry->uuid], get_object_vars($entry))) {
                        return false;
                    }
                }
                return true;
            });
        return true;
    }
}