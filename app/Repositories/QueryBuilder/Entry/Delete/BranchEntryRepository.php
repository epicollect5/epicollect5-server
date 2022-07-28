<?php

namespace ec5\Repositories\QueryBuilder\Entry\Delete;

class BranchEntryRepository extends DeleteBase
{

    /**
     * BranchEntryRepository constructor.
     */
    public function __construct()
    {
        $this->table = 'branch_entries';

        parent::__construct();
    }

}