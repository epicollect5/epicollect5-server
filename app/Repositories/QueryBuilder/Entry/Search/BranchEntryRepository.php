<?php

namespace ec5\Repositories\QueryBuilder\Entry\Search;

use DB;
use ec5\Models\Projects\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;

class BranchEntryRepository extends SearchBase
{

    /**
     * BranchEntryRepository constructor.
     */
    public function __construct()
    {
        DB::connection()->enableQueryLog();
        $this->table = 'branch_entries';

        parent::__construct();
    }

    /**
     * @param $projectId
     * @param $options
     * @param array $columns
     * @return Builder
     */
    public function getEntry($projectId, $options, $columns = array('*'))
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

        return $this->sortAndFilterEntries($q, $options);

    }

    /**
     * @param $projectId
     * @param $options
     * @param array $columns
     * @return Builder
     */
    public function getMapData($projectId, $options, $columns = array('*'))
    {
        $selectSql = 'JSON_EXTRACT(geo_json_data, ?) as geo_json_data ';
        $whereSql = 'project_id = ? AND owner_input_ref = ?';

        $q = DB::table($this->table)
            ->whereRaw($whereSql, [$projectId, $options['branch_ref']])
            ->where(function ($query) use ($options) {
                // If we have a user ID
                if (!empty($options['user_id'])) {
                    $query->where('user_id', '=', $options['user_id']);
                }
            })
            ->selectRaw($selectSql, ['$."' . $options['input_ref'] . '"']);

        return $this->sortAndFilterEntries($q, $options);
    }

    /**
     * @param $projectId
     * @param $options
     * @param array $columns
     * @return Builder
     */
    public function getBranchEntriesForBranchRefAndOwner($projectId, $options, $columns = array('*'))
    {
        $q = DB::table($this->table)
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $options['form_ref'])
            ->where('owner_uuid', '=', $options['branch_owner_uuid'])
            ->where('owner_input_ref', '=', $options['branch_ref'])
            ->where(function ($query) use ($options) {
                // If we have a user ID
                if (!empty($options['user_id'])) {
                    $query->where('user_id', '=', $options['user_id']);
                }
            })
            ->select($columns);

        return $this->sortAndFilterEntries($q, $options);

    }

    /**
     * @param $projectId
     * @param $options
     * @param array $columns
     * @return Builder
     */
    public function getBranchEntriesForBranchOwner($projectId, $options, $columns = array('*'))
    {
        $q = DB::table($this->table)
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $options['form_ref'])
            ->where('owner_uuid', '=', $options['owner_uuid'])
            ->where(function ($query) use ($options) {
                // If we have a user ID
                if (!empty($options['user_id'])) {
                    $query->where('user_id', '=', $options['user_id']);
                }
            })
            ->select($columns);

        return $this->sortAndFilterEntries($q, $options);
    }

    /**
     * @param $projectId
     * @param $options
     * @param array $columns
     * @return Builder
     */
    public function getBranchEntriesForBranchRef($projectId, $options, $columns = array('*'))
    {
        $q = DB::table($this->table)
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

        return $this->sortAndFilterEntries($q, $options);
    }

    /**
     * Get all branch entry uuids for the array of entry uuids
     *
     * @param $projectId
     * @param array $entries
     * @return array
     */
    public function getBranchEntries($projectId, array $entries) : array
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
