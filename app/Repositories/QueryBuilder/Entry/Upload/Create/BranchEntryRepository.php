<?php

namespace ec5\Repositories\QueryBuilder\Entry\Upload\Create;

use ec5\Models\Entries\EntryStructure;

class BranchEntryRepository extends CreateRepository
{

    public function __construct()
    {
        $table = config('epicollect.tables.branch_entries');
        $isBranchEntry = true;

        parent::__construct($table, $isBranchEntry);
    }

    /**
     * @param EntryStructure $entryStructure
     * @param $entry
     * @return int
     */
    protected function insertNewEntry(EntryStructure $entryStructure, $entry): int
    {
        // Add additional keys/values for branch entries
        $entry['owner_entry_id'] = $entryStructure->getBranchOwnerEntryDbId();
        $entry['owner_uuid'] = $entryStructure->getOwnerUuid();
        $entry['owner_input_ref'] = $entryStructure->getOwnerInputRef();

        return parent::insertNewEntry($entryStructure, $entry);
    }

}

