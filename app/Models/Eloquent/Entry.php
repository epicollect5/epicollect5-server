<?php

namespace ec5\Models\Eloquent;

use DB;
use Illuminate\Database\Eloquent\Model;
use ec5\Traits\Eloquent\Entries;

class Entry extends Model
{
    use Entries;

    protected $table = 'entries';
    //disable eloquent timestamps because we are using "uploaded_at"
    public $timestamps = false;

    public static function getEntriesByForm($projectId, $params, $columns = array('*')): \Illuminate\Database\Query\Builder
    {
        $q = DB::table(config('epicollect.strings.database_tables.entries'))
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $params['form_ref'])
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
