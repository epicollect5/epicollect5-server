<?php

namespace ec5\Repositories\QueryBuilder\Entry\Upload\Create;

use ec5\Repositories\QueryBuilder\Stats\Entry\BranchEntryRepository as StatsRepository;

use ec5\Models\Entries\EntryStructure;

use Config;

class BranchEntryRepository extends CreateRepository
{

    /**
     * BranchEntryRepository constructor.
     * @param StatsRepository $statsRepository
     */
    public function __construct(StatsRepository $statsRepository)
    {
        $table = Config::get('ec5Tables.branch_entries');
        $isBranchEntry = true;

        parent::__construct($table, $statsRepository, $isBranchEntry);
    }

    /**
     * @param EntryStructure $entryStructure
     * @param $entry
     * @return int
     */
    protected function insertNewEntry(EntryStructure $entryStructure, $entry)
    {
        // Add additional keys/values for branch entries
        $entry['owner_entry_id'] = $entryStructure->getBranchOwnerEntryDbId();
        $entry['owner_uuid'] = $entryStructure->getOwnerUuid();
        $entry['owner_input_ref'] = $entryStructure->getOwnerInputRef();
        
        return parent::insertNewEntry($entryStructure, $entry);
    }

}

