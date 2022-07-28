<?php

namespace ec5\Repositories\QueryBuilder\Entry\Delete;

use DB;
use ec5\Models\Projects\Project;
use ec5\Repositories\QueryBuilder\Base;

class DeleteBase extends Base
{

    protected $table = '';

    /**
     * ArchiveBase constructor.
     */
    public function __construct()
    {
        DB::connection()->enableQueryLog();

        parent::__construct();
    }

    /**
     * @param $projectId
     * @param $uuids
     * @return bool
     */
    public function delete($projectId, $uuids)
    {
        try {
            // Delete all entries with a uuid in $uuids array
            DB::table($this->table)
                ->where('project_id', '=', $projectId)
                ->whereIn('uuid', $uuids)
                ->delete();

            return true;

        } catch (\Exception $e) {
            $this->errors['entry_delete'] = ['ec5_240'];
            return false;
        }

    }
}
