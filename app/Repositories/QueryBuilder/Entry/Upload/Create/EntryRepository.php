<?php

namespace ec5\Repositories\QueryBuilder\Entry\Upload\Create;

use ec5\Repositories\QueryBuilder\Stats\Entry\EntryRepository as StatsRepository;

use ec5\Models\Entries\EntryStructure;

use Config;

class EntryRepository extends CreateRepository
{
    /**
     * EntryCreateRepository constructor.
     * @param StatsRepository $statsRepository
     */
    public function __construct(StatsRepository $statsRepository)
    {
        $table = Config::get('ec5Tables.entries');
        $isBranchEntry = false;

        parent::__construct($table, $statsRepository, $isBranchEntry);
    }

    /**
     * @param EntryStructure $entryStructure
     * @param $entry
     * @return int
     */
    protected function insertNewEntry(EntryStructure $entryStructure, $entry)
    {
        // Add additional keys/values for entries
        $entry['parent_uuid'] = $entryStructure->getParentUuid();
        $entry['parent_form_ref'] = $entryStructure->getParentFormRef();

        return parent::insertNewEntry($entryStructure, $entry);
    }

}
