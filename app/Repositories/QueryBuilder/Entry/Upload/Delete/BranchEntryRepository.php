<?php

namespace ec5\Repositories\QueryBuilder\Entry\Upload\Delete;

use Config;

class BranchEntryRepository extends DeleteRepository
{

    /**
     * EntryRepository constructor.
     */
    public function __construct()
    {
        $table = config('epicollect.tables.branch_entries');
        $isBranchEntry = true;
        parent::__construct($table, $isBranchEntry);
    }


}
