<?php

namespace ec5\Models\Entries;

use DB;
use ec5\Traits\Eloquent\Entries;
use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

/**
 * @property int $id
 * @property int $project_id
 * @property string $uuid
 * @property int $owner_entry_id
 * @property string $owner_uuid
 * @property string $owner_input_ref
 * @property string $form_ref
 * @property int $user_id
 * @property string $platform
 * @property string $device_id
 * @property string $created_at
 * @property string $uploaded_at
 * @property string $title
 * @property mixed $entry_data
 * @property string $geo_json_data
 */
class BranchEntry extends Model
{
    use Entries;
    use SerializeDates;

    protected $table = 'branch_entries';
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
                if (!empty($options['owner_entry_uuid'])) {
                    $query->where('owner_uuid', '=', $options['owner_entry_uuid']);
                }
            });

        return self::sortAndFilterEntries($q, $options);
    }

    public static function getBranchEntriesByBranchRef($projectId, $params, $columns = array('*')): Builder
    {
        $q = DB::table(config('epicollect.tables.branch_entries'))
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

    public static function getBranchEntriesByBranchRefOP($projectId, $params, $columns = array('*')): Builder
    {
        // Ensure 'id' is included in the columns
        if (!in_array('id', $columns)) {
            $columns[] = 'id';
        }
        // Optimized version without user_id filtering and sorting for better performance during bulk exports
        return DB::table(config('epicollect.tables.branch_entries'))
            ->where('project_id', '=', $projectId)
            ->where('form_ref', '=', $params['form_ref'])
            ->where('owner_input_ref', '=', $params['branch_ref'])
            ->select($columns);
    }

    /**
     * Get all branch entry uuids for the array of entry uuids
     *
     * @param $projectId
     * @param array $hierarchyEntriesUuids
     * @return array
     */
    public function getBranchEntriesUuids($projectId, array $hierarchyEntriesUuids): array
    {
        // Array of all the entry uuids we'll collect
        $branchEntriesUuids = [];

        // Get all branch entries, chunking data (batches of 100)
        // NOTE: we need the same $uuids array, so pass by reference
        DB::table($this->table)
            ->where('project_id', '=', $projectId)
            ->whereIn('owner_uuid', $hierarchyEntriesUuids)
            ->select('uuid')
            ->orderBy('created_at', 'DESC')
            ->chunk(100, function ($data) use (&$branchEntriesUuids) {
                foreach ($data as $entry) {
                    // Add uuid to array
                    $branchEntriesUuids[] = $entry->uuid;
                }
            });
        return $branchEntriesUuids;
    }

    /**
     * @param $projectId
     * @param $options
     * @param array $columns
     * @return Builder
     */
    public function getBranchEntriesForBranchRefAndOwner($projectId, $options, array $columns = array('*')): Builder
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
}
