<?php

namespace ec5\Repositories\QueryBuilder\Entry\Upload\Delete;

use Config;

class EntryRepository extends DeleteRepository
{

    /**
     * EntryRepository constructor.
     */
    public function __construct()
    {
        $table = config('epicollect.tables.entries');
        $isBranchEntry = false;
        parent::__construct($table, $isBranchEntry);
    }

}
