<?php

namespace ec5\Models\Entries;

use DB;
use ec5\Services\Entries\EntriesViewService;
use ec5\Traits\Eloquent\Entries;
use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

/**
 * @property int $id
 * @property int $project_id
 * @property string $uuid
 * @property string $parent_uuid
 * @property string $form_ref
 * @property string $parent_form_ref
 * @property int $user_id
 * @property string $platform
 * @property string $device_id
 * @property string $created_at
 * @property string $uploaded_at
 * @property string $title
 * @property mixed $entry_data
 * @property string $geo_json_data
 * @property string $child_counts
 * @property mixed $branch_counts
 */
class Entry extends Model
{
    use Entries;
    use SerializeDates;

    protected $table = 'entries';
    /**
     *  Disable eloquent timestamps because we are using
     * "uploaded_at" -> when entry is uploaded or edited
     * "created_at" -> copied from the entry created_at (mobile) or set when entry is saved (web)
     */
    public $timestamps = false;

    /**
     * Casting to a datetime ISO 8601 without milliseconds
     * due to legacy reasons
     */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'uploaded_at' => 'datetime:Y-m-d H:i:s',
    ];

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
                if (!empty($options['parent_entry_uuid'])) {
                    $query->where('parent_uuid', '=', $options['parent_entry_uuid']);
                }
            });

        return self::sortAndFilterEntries($q, $options);
    }

    /**
     * @param $projectId
     * @param $params
     * @return Builder
     */
    public function getGeoJsonData($projectId, $params): Builder
    {
        $selectSql = 'JSON_EXTRACT(geo_json_data, ?) as geo_json_data ';
        $whereSql = 'project_id = ?';

        //get all location data
        $q = DB::table($this->table)
            ->whereRaw($whereSql, [$projectId])
            ->selectRaw($selectSql, ['$."' . $params['input_ref'] . '"']);

        //filter by user (imp: applied to COLLECTOR ROLE ONLY)
        /**
         * @see EntriesViewService::getSanitizedQueryParams
         */
        if (!empty($params['user_id'])) {
            $q->where('user_id', '=', $params['user_id']);
        }

        return self::sortAndFilterEntries($q, $params);
    }

    /**
     * @param $projectId
     * @param $options
     * @param array $columns
     * @return Builder
     */
    public function getChildEntriesForParent($projectId, $options, array $columns = array('*')): Builder
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
    public function getEntries($projectId, $options, array $columns = array('*')): Builder
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

    /**
     * Retrieves entries for the specified project and form.
     *
     * Constructs a query on the entries table, filtering by the project identifier and form reference.
     * Optionally filters by user ID if provided, and applies additional sorting and filtering via the sortAndFilterEntries method.
     *
     * @param mixed $projectId The project identifier.
     * @param array $params An array containing query parameters; must include a 'form_ref' key and may include a 'user_id' key.
     * @param array $columns The list of columns to retrieve; defaults to ['*'].
     * @return \Illuminate\Database\Query\Builder The query builder instance for the constructed entries query.
     */
    public static function getEntriesByForm($projectId, $params, $columns = array('*')): Builder
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
     * Retrieves entries for a specific form, ensuring the 'id' column is always selected.
     *
     * This method constructs a query on the entries table using the given project identifier and form reference
     * from the parameters. It guarantees that the list of selected columns includes 'id', appending it if absent.
     *
     * @param mixed $projectId The identifier for the project.
     * @param array $params Array of parameters that must include a 'form_ref' key for filtering entries by form.
     * @param array $columns Optional list of columns to select; defaults to all columns.
     *
     * @return \Illuminate\Database\Query\Builder Query builder instance for retrieving the filtered entries.
     */
    public static function getEntriesByFormOP($projectId, $params, $columns = array('*')): Builder
    {
        // Ensure 'id' is included in the columns
        if (!in_array('id', $columns)) {
            $columns[] = 'id';
        }
        // Optimized version without user_id filtering and sorting for better performance during bulk exports
        return DB::table(config('epicollect.tables.entries'))
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $params['form_ref'])
            ->select($columns);
    }

    /**
     * Get the parent given a parent entry uuid and form ref
     *
     * @param $parentEntryUuid
     * @param $parentFormRef
     * @return mixed
     */
    public function getParentEntry($parentEntryUuid, $parentFormRef): mixed
    {
        return DB::table($this->table)
            ->where('uuid', '=', $parentEntryUuid)
            ->where('form_ref', '=', $parentFormRef)
            ->first();
    }
}
