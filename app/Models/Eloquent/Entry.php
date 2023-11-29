<?php

namespace ec5\Models\Eloquent;

use DB;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ec5\Traits\Eloquent\Entries;

/**
 * Class Entry
 * @package ec5\Models\Eloquent
 */
class Entry extends Model
{
    use Entries;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'entries';
    //disable eloquent timestamps because we are using "uploaded_at"
    public $timestamps = false;

    public static function getEntriesByForm($projectId, $options, $columns = array('*')): \Illuminate\Database\Query\Builder
    {
        $q = DB::table(config('epicollect.strings.database_tables.entries'))
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $options['form_ref'])
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
