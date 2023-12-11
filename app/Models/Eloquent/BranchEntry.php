<?php

namespace ec5\Models\Eloquent;

use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use ec5\Traits\Eloquent\Entries;

class BranchEntry extends Model
{
    use Entries;

    protected $table = 'branch_entries';
    //disable eloquent timestamps because we are using "uploaded_at"
    public $timestamps = false;

    public static function getBranchEntriesByBranchRef($projectId, $params, $columns = array('*')): Builder
    {
        $q = DB::table(config('epicollect.strings.database_tables.branch_entries'))
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $params['form_ref'])
            ->where('owner_input_ref', '=', $params['branch_ref'])
            ->where(function ($query) use ($params) {
                // If we have a user ID
                if (!empty($params['user_id'])) {
                    $query->where('user_id', '=', $params['user_id']);
                }
            })
            ->select($columns);

        return self::sortAndFilterEntries($q, $params);
    }
}
