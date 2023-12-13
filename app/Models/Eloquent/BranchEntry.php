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

    /**
     * @param $projectId
     * @param $options
     * @param array $columns
     * @return Builder
     */
    public function getEntry($projectId, $options, array $columns = array('*')): Builder
    {
        $q = DB::table($this->table)->select($columns)
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $options['form_ref'])
            ->where('uuid', '=', $options['uuid'])
            ->where(function ($query) use ($options) {
                // If we have a user ID
                if (!empty($options['user_id'])) {
                    $query->where('user_id', '=', $options['user_id']);
                }
            })->where(function ($query) use ($options) {
                // If we have an owner uuid
                if (!empty($options['owner_entry_uuid'])) {
                    $query->where('owner_uuid', '=', $options['owner_entry_uuid']);
                }
            });

        return self::sortAndFilterEntries($q, $options);
    }

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

    /**
     * Get all branch entry uuids for the array of entry uuids
     *
     * @param $projectId
     * @param array $entries
     * @return array
     */
    public function getBranchEntriesUuids($projectId, array $entries): array
    {
        // Array of all the entry uuids we'll collect
        $uuids = [];

        // Get all branch entries, chunking data (batches of 100)
        // NOTE: we need the same $uuids array, so pass by reference
        DB::table($this->table)
            ->where('project_id', '=', $projectId)
            ->whereIn('owner_uuid', $entries)
            ->select('uuid')
            ->orderBy('created_at', 'DESC')
            ->chunk(100, function ($data) use (&$uuids) {
                foreach ($data as $entry) {
                    // Add uuid to array
                    $uuids[] = $entry->uuid;
                }
            });
        return $uuids;
    }
}
