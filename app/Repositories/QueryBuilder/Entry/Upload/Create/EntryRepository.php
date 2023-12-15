<?php

namespace ec5\Repositories\QueryBuilder\Entry\Upload\Create;

use ec5\Models\Entries\EntryStructure;

class EntryRepository extends CreateRepository
{
    public function __construct()
    {
        $table = config('epicollect.tables.entries');
        $isBranchEntry = false;

        parent::__construct($table, $isBranchEntry);
    }

    /**
     * @param EntryStructure $entryStructure
     * @param $entry
     * @return int
     */
    protected function insertNewEntry(EntryStructure $entryStructure, $entry): int
    {
        // Add additional keys/values for entries
        $entry['parent_uuid'] = $entryStructure->getParentUuid();
        $entry['parent_form_ref'] = $entryStructure->getParentFormRef();

        return parent::insertNewEntry($entryStructure, $entry);
    }
}
