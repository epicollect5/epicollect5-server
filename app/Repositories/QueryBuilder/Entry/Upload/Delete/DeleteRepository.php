<?php namespace ec5\Repositories\QueryBuilder\Entry\Upload\Delete;

use ec5\Repositories\QueryBuilder\Base;
use DB;
use Config;

abstract class DeleteRepository extends Base
{

    /**
     * @var
     */
    protected $table;

    /**
     * @var
     */
    protected $isBranchEntry;

    /**
     * DeleteRepository constructor.
     * @param $table
     * @param $isBranchEntry
     */
    public function __construct($table, $isBranchEntry)
    {
        $this->table = $table;
        $this->isBranchEntry = $isBranchEntry;
        parent::__construct();
    }

}