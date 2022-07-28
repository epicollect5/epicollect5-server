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
        $table = Config::get('ec5Tables.entries');
        $isBranchEntry = false;
        parent::__construct($table, $isBranchEntry);
    }

}
