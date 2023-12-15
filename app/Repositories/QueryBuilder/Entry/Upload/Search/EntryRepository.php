<?php

namespace ec5\Repositories\QueryBuilder\Entry\Upload\Search;

use Config;
use DB;

class EntryRepository extends SearchRepository
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

    /**
     * Get the parent given a parent entry uuid and form ref
     *
     * @param $parentEntryUuid
     * @param $parentFormRef
     * @return mixed
     */
    public function getParentEntry($parentEntryUuid, $parentFormRef)
    {
        return DB::table($this->table)
            ->where('uuid', '=', $parentEntryUuid)
            ->where('form_ref', '=', $parentFormRef)
            ->first();
    }

}
