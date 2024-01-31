<?php

namespace ec5\Models\Entries;

use DB;
use ec5\Traits\Eloquent\Entries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class Entry extends Model
{
    use Entries;

    protected $table = 'entries';
    //disable eloquent timestamps because we are using "uploaded_at"
    public $timestamps = false;

    /**
     * @param $projectId
     * @param $options
     * @param array $columns
     * @return Builder
     */
    public function getEntry($projectId, $options, $columns = array('*')): Builder
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
                if (!empty($options['parent_entry_uuid'])) {
                    $query->where('parent_uuid', '=', $options['parent_entry_uuid']);
                }
            });

        return self::sortAndFilterEntries($q, $options);

    }

    /**
     * @param $projectId
     * @param $options
     * @param array $columns
     * @return Builder
     */
    public function getMapData($projectId, $options, $columns = array('*')): Builder
    {
        $selectSql = 'JSON_EXTRACT(geo_json_data, ?) as geo_json_data ';
        $whereSql = 'project_id = ?';

        //get all location data
        $q = DB::table($this->table)
            ->whereRaw($whereSql, [$projectId])
            ->selectRaw($selectSql, ['$."' . $options['input_ref'] . '"']);

        //filter by user (COLLECTOR ROLE ONLY)
        if (!empty($options['user_id'])) {
            $q->where('user_id', '=', $options['user_id']);
        }

        return self::sortAndFilterEntries($q, $options);
    }

    /**
     * @param $projectId
     * @param $options
     * @param array $columns
     * @return Builder
     */
    public function getChildEntriesForParent($projectId, $options, $columns = array('*'))
    {
        $q = DB::table($this->table)
            ->where('project_id', '=', $projectId)
            ->where('parent_uuid', '=', $options['parent_uuid'])
            ->where('form_ref', '=', $options['form_ref'])
            ->where('parent_form_ref', '=', $options['parent_form_ref'])
            ->where(function ($query) use ($options) {
                // If we have a user ID
                if (!empty($options['user_id'])) {
                    $query->where('user_id', '=', $options['user_id']);
                }
            })
            ->select($columns);

        if (!empty($options['input_ref'])) {
            $q = $this->createFilterOptions($q, $options);
        }
        return self::sortAndFilterEntries($q, $options);
    }

    /**
     * Get all child (descendants) entry uuids for a single entry uuid
     *
     * @param $projectId
     * @param array $uuids
     * @param $uuid
     * @return array
     */
    public function getChildEntriesUuids($projectId, array $uuids, $uuid): array
    {
        // Get all child entries, chunking data (batches of 100)
        // NOTE: we need the same $uuids array, so pass by reference
        DB::table(config('epicollect.tables.entries'))
            ->where('project_id', '=', $projectId)
            ->where('parent_uuid', '=', $uuid)
            ->select('uuid')
            ->orderBy('created_at', 'DESC')
            ->chunk(100, function ($data) use (&$uuids, $projectId) {
                foreach ($data as $entry) {
                    // Get the next set of related child entries recursively
                    $uuids = $this->getChildEntriesUuids($projectId, $uuids, $entry->uuid);
                    // Add uuid to array
                    $uuids[] = $entry->uuid;
                }
            });

        return $uuids;
    }


    /**
     * @param $projectId
     * @param $options
     * @param array $columns
     * @return Builder
     */
    public function getEntries($projectId, $options, $columns = array('*'))
    {
        $q = DB::table($this->table)
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $options['form_ref'])
            ->where(function ($query) use ($options) {
                // If we have a user ID
                if (!empty($options['user_id'])) {
                    $query->where('user_id', '=', $options['user_id']);
                }
            })
            ->select($columns);

        if (!empty($options['input_ref'])) {
            $q = $this->createFilterOptions($q, $options);
        }

        return $this->sortAndFilterEntries($q, $options);
    }

    public static function getEntriesByForm($projectId, $params, $columns = array('*')): \Illuminate\Database\Query\Builder
    {
        $q = DB::table(config('epicollect.tables.entries'))
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
