<?php

namespace ec5\Repositories\QueryBuilder\Entry\Archive;

use ec5\Repositories\QueryBuilder\Entry\Search\EntryRepository as EntrySearch;
use ec5\Repositories\QueryBuilder\Entry\Delete\EntryRepository as EntryDelete;
use ec5\Repositories\QueryBuilder\Entry\Archive\BranchEntryRepository as BranchEntryArchive;
use ec5\Repositories\QueryBuilder\Entry\Search\BranchEntryRepository as BranchEntrySearch;

class EntryRepository extends ArchiveBase
{

    /**
     * @var EntrySearch
     */
    protected $entrySearch;

    /**
     * @var BranchEntrySearch
     */
    protected $branchEntrySearch;

    /**
     * @var BranchEntryArchive
     */
    protected $branchEntryArchive;

    /**
     * EntryRepository constructor.
     * @param EntrySearch $entrySearch
     * @param EntryDelete $entryDelete
     * @param BranchEntryRepository $branchEntryArchive
     * @param BranchEntrySearch $branchEntrySearch
     */
    public function __construct(EntrySearch $entrySearch,
                                EntryDelete $entryDelete,
                                BranchEntryArchive $branchEntryArchive,
                                BranchEntrySearch $branchEntrySearch)
    {
        $this->table = 'entries';
        $this->entrySearch = $entrySearch;
        $this->branchEntryArchive = $branchEntryArchive;
        $this->branchEntrySearch = $branchEntrySearch;

        parent::__construct($entryDelete);
    }

    /**
     * @param $projectId
     * @param $formRef
     * @param $entryUuid - entry uuid (and all relations, branches) we need to archive
     * @param bool $keepOpenTransaction
     * @return bool
     */
    public function archive($projectId, $formRef, $entryUuid, $keepOpenTransaction = false)
    {
        $this->startTransaction();

        // 1. Gather all entries related to this entry (including the original entry_uuid)
        // Related entries (descendants)
        $entryUuids = $this->entrySearch->getRelatedEntries($projectId, [$entryUuid], $entryUuid);

        // 2. Copy the entries to the archive table
        $this->copy($projectId, $entryUuids);

        // 3. Get the Branch entriy uuids
        $branchEntryUuids = $this->branchEntrySearch->getBranchEntries($projectId, $entryUuids);

        // 4. Archive the branch entries
        foreach ($branchEntryUuids as $branchEntryUuid) {
            // Attempt to archive
            if (!$this->branchEntryArchive->archive($projectId, $formRef, $branchEntryUuid, true)) {
                $this->errors = $this->branchEntryArchive->errors();
                return false;
            }
        }

        // 5. Finally delete the main entries
        $this->delete($projectId, $entryUuids);

        // Rollback if errors
        if ($this->hasErrors()) {
            $this->doRollBack();
            return false;
        }

        // Otherwise commit
        $this->doCommit();
        return true;
    }

}