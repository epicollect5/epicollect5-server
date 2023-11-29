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

    public static function getBranchEntriesByBranchRef($projectId, $options, $columns = array('*')): Builder
    {
        $q = DB::table(config('epicollect.strings.database_tables.branch_entries'))
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $options['form_ref'])
            ->where('owner_input_ref', '=', $options['branch_ref'])
            ->where(function ($query) use ($options) {
                // If we have a user ID
                if (!empty($options['user_id'])) {
                    $query->where('user_id', '=', $options['user_id']);
                }
            })
            ->select($columns);

        return self::sortAndFilterEntries($q, $options);
    }
}
