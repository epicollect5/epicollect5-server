<?php

namespace ec5\Repositories\QueryBuilder\Entry\Archive;

use ec5\Repositories\QueryBuilder\Entry\Delete\BranchEntryRepository as BranchEntryDelete;

class BranchEntryRepository extends ArchiveBase
{

    /**
     * BranchEntryRepository constructor.
     * @param BranchEntryDelete $branchEntryDelete
     */
    public function __construct(BranchEntryDelete $branchEntryDelete)
    {
        $this->table = 'branch_entries';

        parent::__construct($branchEntryDelete);
    }

    /**
     * @param $projectId
     * @param $formRef
     * @param $entryUuid - entry uuid we need to archive
     * @param $keepOpenTransaction
     * @return bool
     */
    public function archive($projectId, $formRef, $entryUuid, $keepOpenTransaction = false)
    {
        // If we don't want to keep open the transaction for further processing
        if (!$keepOpenTransaction) {
            $this->startTransaction();
        }

        // Copy then delete the branch entries
        $this->copy($projectId, [$entryUuid]);
        $this->delete($projectId, [$entryUuid]);

        // Rollback if errors
        if ($this->hasErrors()) {
            $this->doRollBack();
            return false;
        }

        // If we don't want to keep open the transaction for further processing
        // Commit
        if (!$keepOpenTransaction) {
            $this->doCommit();
        }
        return true;


    }

}