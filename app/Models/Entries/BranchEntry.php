<?php

namespace ec5\Models\Entries;

use DB;
use ec5\Traits\Eloquent\Entries;
use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
     * Get the branch entry's json data from branch_entries_json table
     */
    public function json(): HasOne
    {
        return $this->hasOne(BranchEntryJson::class, 'entry_id');
    }

    /**
     * Transparent accessor for entry_data.
     * Uses entries_json if inline data is null.
     * ⚠️ For performance, always eager-load 'json' in bulk queries.
     */
    public function getEntryDataAttribute($value)
    {
        if (!is_null($value)) {
            return $value;
        }

        if ($this->relationLoaded('json')) {
            return $this->json?->entry_data;
        }

        // Avoid N+1 in loops — use eager loading where possible
        return $this->json()->value('entry_data');
    }

    /**
     * Transparent accessor for geo_json_data.
     * Falls back to entries_json.geo_json_data if null.
     * ⚠️ Always eager-load 'json' to avoid N+1 queries in bulk reads.
     */
    public function getGeoJsonDataAttribute($value)
    {
        if (!is_null($value)) {
            return $value;
        }

        if ($this->relationLoaded('json')) {
            return $this->json?->geo_json_data;
        }

        // Lazy-load as a last resort (1 query)
        return $this->json()->value('geo_json_data');
    }

    /**
     * @param $projectId
     * @param $options
     * @param array $columns
     * @return Builder
     */
    public function getEntry($projectId, $options, array $columns = array('*')): Builder
    {
        // Remove entry_data and geo_json_data from $columns completely
        // We do this for the COALESCE to work properly
        $columns = array_diff($columns, ['entry_data', 'geo_json_data']);

        $q = DB::table($this->table . ' as be')
            ->leftJoin('branch_entries_json as bej', 'be.id', '=', 'bej.entry_id')
            ->select(array_merge(
                $columns,
                [
                    DB::raw('COALESCE(be.entry_data, bej.entry_data) as entry_data'),
                    DB::raw('COALESCE(be.geo_json_data, bej.geo_json_data) as geo_json_data')
                ]
            ))
            ->where('be.project_id', '=', $projectId)
            ->where('be.form_ref', '=', $options['form_ref'])
            ->where('be.uuid', '=', $options['uuid'])
            ->where(function ($query) use ($options) {
                // If we have a user ID
                if (!empty($options['user_id'])) {
                    $query->where('be.user_id', '=', $options['user_id']);
                }
            })->where(function ($query) use ($options) {
                // If we have an owner uuid
                if (!empty($options['owner_entry_uuid'])) {
                    $query->where('be.owner_uuid', '=', $options['owner_entry_uuid']);
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
        // Remove entry_data and geo_json_data from $columns completely
        // We do this for the COALESCE to work properly
        $columns = array_diff($columns, ['entry_data', 'geo_json_data']);

        $q = DB::table(config('epicollect.tables.branch_entries') . ' as be')
            ->leftJoin('branch_entries_json as bej', 'be.id', '=', 'bej.entry_id')
            ->select(array_merge(
                $columns,
                [
                    DB::raw('COALESCE(be.entry_data, bej.entry_data) as entry_data'),
                    DB::raw('COALESCE(be.geo_json_data, bej.geo_json_data) as geo_json_data')
                ]
            ))
            ->where('be.project_id', '=', $projectId)
            ->where('be.form_ref', '=', $params['form_ref'])
            ->where('be.owner_input_ref', '=', $params['branch_ref'])
            ->where(function ($query) use ($params) {
                // If we have a user ID
                if (!empty($params['user_id'])) {
                    $query->where('be.user_id', '=', $params['user_id']);
                }
            });

        return $this->sortAndFilterEntries($q, $params);
    }

    /**
     * Builds a query to retrieve branch entries for archive downloads, filtered by project, form reference, branch reference, and optionally user.
     *
     * Ensures the 'id' column is included in the selection and applies a forced index for optimized performance. No sorting is applied, but time-based filtering is handled for archive purposes.
     *
     * @param int $projectId The project identifier.
     * @param array $params Must include 'form_ref' (form reference) and 'branch_ref' (branch/owner input reference); may include 'user_id' for user-specific filtering.
     * @param array $columns Columns to select; 'id' is always included.
     * @return Builder Query builder instance for further chaining or execution.
     */
    public function getBranchEntriesByBranchRefForArchive(int $projectId, array $params, array $columns = array('*')): Builder
    {
        // Ensure 'id' is included in the columns
        if (!in_array('id', $columns)) {
            $columns[] = 'id';
        }

        // Remove entry_data and geo_json_data from $columns completely
        // We do this for the COALESCE to work properly
        $columns = array_diff($columns, ['entry_data', 'geo_json_data']);

        // Optimized version without user_id filtering and sorting for better performance during bulk exports
        //Use raw SQL to apply FORCE INDEX
        $q = DB::table(DB::raw(config('epicollect.tables.branch_entries') . ' as be FORCE INDEX (idx_branch_entries_project_form_ref_id)'))
            ->leftJoin('branch_entries_json as bej', 'be.id', '=', 'bej.entry_id')
            ->select(array_merge(
                $columns,
                [
                    DB::raw('COALESCE(be.entry_data, bej.entry_data) as entry_data'),
                    DB::raw('COALESCE(be.geo_json_data, bej.geo_json_data) as geo_json_data')
                ]
            ))
            ->where('be.project_id', '=', $projectId)
            ->where('be.form_ref', '=', $params['form_ref'])
            ->where('be.owner_input_ref', '=', $params['branch_ref'])
            ->where(function ($query) use ($params) {
                // If we have a user ID
                if (!empty($params['user_id'])) {
                    $query->where('be.user_id', '=', $params['user_id']);
                }
            });

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
        // Remove entry_data and geo_json_data from $columns completely
        // We do this for the COALESCE to work properly
        $columns = array_diff($columns, ['entry_data', 'geo_json_data']);

        $q = DB::table($this->table . ' as be')
            ->leftJoin('branch_entries_json as bej', 'be.id', '=', 'bej.entry_id')
            ->select(array_merge(
                $columns,
                [
                    DB::raw('COALESCE(be.entry_data, bej.entry_data) as entry_data'),
                    DB::raw('COALESCE(be.geo_json_data, bej.geo_json_data) as geo_json_data')
                ]
            ))
            ->where('be.project_id', '=', $projectId)
            ->where('be.form_ref', '=', $options['form_ref'])
            ->where('be.owner_uuid', '=', $options['branch_owner_uuid'])
            ->where('be.owner_input_ref', '=', $options['branch_ref'])
            ->where(function ($query) use ($options) {
                // If we have a user ID
                if (!empty($options['user_id'])) {
                    $query->where('be.user_id', '=', $options['user_id']);
                }
            });

        return $this->sortAndFilterEntries($q, $options);
    }
}
