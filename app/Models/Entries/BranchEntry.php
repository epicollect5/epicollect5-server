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

        return $this->sortAndFilterEntries($q, $options);
    }

    /**
     * Retrieve branch entries by branch reference.
     *
     * Builds a query to select entries from the branch entries table for a specific project,
     * filtering by form reference and branch reference (owner input reference). If a user ID is provided
     * in the parameters, the query further restricts the results to that user. The query is then processed
     * by applying additional sorting and filtering.
     *
     * @param int $projectId The unique identifier for the project.
     * @param array $params {
     *     Array of filtering criteria.
     *
     *     @type string $form_ref   The form reference used to select entries.
     *     @type string $branch_ref The branch reference (owner input reference) to filter entries.
     *     @type int|null $user_id  Optional user identifier for narrowing down the results.
     * }
     */
    public function getBranchEntriesByBranchRef(int $projectId, array $params, $columns = array('*')): Builder
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

        return $this->sortAndFilterEntries($q, $params);
    }

    /**
     * Retrieves branch entries for archive downloads
     *
     * This method builds a query on the branch entries table (as defined in configuration)
     * filtered by the provided project ID, form reference, and branch reference.
     * It ensures that the 'id' column is always included in the selection.
     *
     *  No sorting as this is for downloading archive only
     *
     * @param int $projectId The identifier of the project.
     * @param array $params Associative array with keys:
     *                      - 'form_ref': The form reference used for filtering.
     *                      - 'branch_ref': The branch (owner input) reference for filtering.
     * @param array $columns The list of columns to select; defaults to all columns.
     * @return Builder The query builder instance for the branch entries.
     */
    public function getBranchEntriesByBranchRefForArchive(int $projectId, array $params, array $columns = array('*')): Builder
    {
        // Ensure 'id' is included in the columns
        if (!in_array('id', $columns)) {
            $columns[] = 'id';
        }
        // Optimized version without user_id filtering and sorting for better performance during bulk exports
        //Use raw SQL to apply FORCE INDEX
        $q = DB::table(DB::raw(config('epicollect.tables.branch_entries') . ' FORCE INDEX (idx_branch_entries_project_form_ref_id)'))
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

        //filtering needed for different timeframe downloads (today,month, year...)
        return $this->filteringForArchive($q, $params);
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
