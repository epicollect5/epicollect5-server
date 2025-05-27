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
     * Retrieves a query builder for a specific entry using the given project ID and options.
     *
     * This method builds a database query to select an entry from the entries table by matching the
     * project identifier with a specified form reference and UUID. It optionally applies additional filters
     * for user and parent entry identifiers if provided in the options. The query is then processed with
     * sorting and filtering adjustments.
     *
     * @param int $projectId Identifier of the project.
     * @param array $options Array containing query parameters:
     *                       - 'form_ref': Form reference for filtering.
     *                       - 'uuid': Unique identifier of the entry.
     *                       - 'user_id' (optional): Identifier of the user for additional filtering.
     *                       - 'parent_entry_uuid' (optional): UUID of the parent entry for additional filtering.
     * @param array $columns List of columns to select; defaults to all columns.
     *
     */
    public function getEntry(int $projectId, array $options, array $columns = array('*')): Builder
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

        return $this->sortAndFilterEntries($q, $options);
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
        return $this->sortAndFilterEntries($q, $options);
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
     * Retrieves entries for the specified project and form.
     *
     * Constructs a query on the entries table, filtering by the project identifier and form reference.
     * Optionally filters by user ID if provided, and applies additional sorting and filtering via the sortAndFilterEntries method.
     *
     * @param int $projectId The project identifier.
     * @param array $params An array containing query parameters; must include a 'form_ref' key and may include a 'user_id' key.
     * @param array $columns The list of columns to retrieve; defaults to ['*']
     */
    public function getEntriesByForm(int $projectId, array $params, array $columns = array('*')): Builder
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

        return $this->sortAndFilterEntries($q, $params);
    }

    /**
     * Builds a query to retrieve entries for a specific form and project for archive downloads.
     *
     * Ensures the 'id' column is included in the selected columns and applies a forced index for performance. Filters entries by project ID and form reference, and optionally by user ID if provided in the parameters. Additional filtering for archive timeframes is applied. No sorting is performed.
     *
     * @param int $projectId The project identifier.
     * @param array $params Parameters including 'form_ref' and optionally 'user_id' for filtering.
     * @param array $columns Columns to select; defaults to all columns.
     * @return Builder Query builder for the filtered entries.
     */
    public function getEntriesByFormForArchive(int $projectId, array $params, array $columns = array('*')): Builder
    {
        // Ensure 'id' is included in the columns
        if (!in_array('id', $columns)) {
            $columns[] = 'id';
        }

        // Use raw SQL to apply FORCE INDEX
        $q = DB::table(DB::raw(config('epicollect.tables.entries') . ' FORCE INDEX (idx_entries_project_form_ref_id)'))
             ->where('project_id', '=', $projectId)
             ->where('form_ref', '=', $params['form_ref'])
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
