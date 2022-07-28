<?php

namespace ec5\Repositories\QueryBuilder\Entry\Delete;

class EntryRepository extends DeleteBase
{

    /**
     * EntryRepository constructor.
     */
    public function __construct()
    {
        $this->table = 'entries';

        parent::__construct();
    }

}