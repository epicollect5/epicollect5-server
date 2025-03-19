<?php

namespace ec5\Traits\Eloquent;

use Carbon\Carbon;
use DB;
use ec5\DTO\EntryStructureDTO;
use Illuminate\Database\Query\Builder;
use Log;
use Throwable;

trait Entries
{
    /**
     * Retrieves GeoJSON data for entries associated with a specific project.
     *
     * Constructs a query to extract GeoJSON data using the provided project ID and JSON key reference (via 'input_ref').
     * If a 'user_id' is supplied in the parameters, the results are further filtered to include only entries corresponding
     * to that user. The query is subsequently modified with additional sorting and filtering by calling sortAndFilterEntries.
     *
     * @param int $projectId The identifier of the project.
     * @param array $params   Query parameters, including:
     *                        - 'input_ref': The JSON key used to extract data from the geo_json_data field.
     *                        - 'user_id' (optional): Filters entries by user ID if provided.
     *
     */
    public function getGeoJsonData(int $projectId, array $params): Builder
    {
        /**
         * Example of data:
         *
         * {
         *   "913e1f06a69e4718b3625bb3518e4672_67c58cb5c6e96_67c58cc7c0912_67c58cd0c0913": {
         *     "id": "653c25d0-f81f-11ef-adfb-0b667d454f81",
         *     "type": "Feature",
         *     "geometry": {
         *       "type": "Point",
         *       "coordinates": [10.565543, 45.458595]
         *     },
         *     "properties": {
         *       "uuid": "653c25d0-f81f-11ef-adfb-0b667d454f81",
         *       "title": "653c25d0-f81f-11ef-adfb-0b667d454f81",
         *       "accuracy": 4,
         *       "created_at": "2025-03-03",
         *       "possible_answers": []
         *     }
         *   },
         *   {...}
         * }
         */

        $index = 'idx_'.$this->table.'_project_form_ref_id';
        return DB::table($this->table)
            ->select('id', 'geo_json_data')
            ->from(DB::raw("`$this->table` USE INDEX ($index)"))
            ->where('project_id', $projectId)
            ->where('form_ref', $params['form_ref'])
            ->whereNotNull('geo_json_data')
            ->where(function ($query) use ($params) {
                /**
                 * filter by user (imp: applied to COLLECTOR ROLE ONLY)
                 *
                 * @see EntriesViewService::getSanitizedQueryParams
                 */
                if (!empty($params['user_id'])) {
                    $query->where('user_id', '=', $params['user_id']);
                }
            });

    }

    public function getNewestOldestCreatedAt($projectId, $formRef): array
    {
        //format date to match javascript default ISO8601 format
        $formatDate = fn ($date) => $date
            ? str_replace(' ', 'T', Carbon::parse($date)->format('Y-m-d H:i:s')) . '.000Z'
            : null;

        //get oldest date
        $oldest = DB::table('entries')
            ->where('project_id', $projectId)
            ->where('form_ref', $formRef)
            ->whereNotNull('geo_json_data')
            ->orderBy('created_at', 'asc')
            ->limit(1)
            ->value('created_at');

        //get newest date
        $newest = DB::table('entries')
            ->where('project_id', $projectId)
            ->where('form_ref', $formRef)
            ->whereNotNull('geo_json_data')
            ->orderBy('created_at', 'desc')
            ->limit(1)
            ->value('created_at');

        return [
            'oldest' => $formatDate($oldest),
            'newest' => $formatDate($newest),
        ];
    }

    /**
     * Search for entries based on answers
     *
     * @param Builder $q
     * @param $options
     * @return Builder
     */
    protected function createFilterOptions(Builder $q, $options): Builder
    {
        $inputRef = (empty($options['input_ref'])) ? null : $options['input_ref'];
        $search = (empty($options['search'])) ? null : $options['search'];

        if ($inputRef == null || $search == null) {
            return $q;
        }

        $sql = ' json_unquote(JSON_EXTRACT(entry_data, \'$.entry."';
        $q->whereRaw($sql . $inputRef . '"."answer"\')) = ?', [$search]);

        return $q;
    }


    public function sortAndFilterEntries(Builder $q, $filters): Builder
    {
        // Filtering
        if (!empty($filters['filter_by'])) {
            // Filter between
            if (!empty($filters['filter_from']) && !empty($filters['filter_to'])) {
                //create artificial dates using Carbon modifiers
                //to include the full range of entries
                $from = Carbon::parse($filters['filter_from'])->startOfDay();
                $to = Carbon::parse($filters['filter_to'])->endOfDay();
                $q->whereBetween($filters['filter_by'], [$from, $to]);
            } // Filter from
            else {
                if (!empty($filters['filter_from'])) {
                    $q->where($filters['filter_by'], '>=', $filters['filter_from']);
                } // Filter to
                else {
                    if (!empty($filters['filter_to'])) {
                        $q->where($filters['filter_by'], '<=', $filters['filter_to']);
                    }
                }
            }
        }
        //filter by title
        if (!empty($filters['title'])) {
            $q->where('title', 'LIKE', '%' . $filters['title'] . '%');
        }
        // Sorting
        if (!empty($filters['sort_by']) && !empty($filters['sort_order'])) {
            if ($filters['sort_by'] === 'title') {
                //handle the natural sort on alphanumeric titles -> t.ly/tl5X
                $q->orderByRaw('LENGTH(' . $filters['sort_by'] . ') ' . $filters['sort_order'] . ' , ' . $filters['sort_by'] . ' ' . $filters['sort_order']);
            } else {
                $q->orderBy($filters['sort_by'], $filters['sort_order']);
            }
        } else {
            //default sorting, most recent first
            $q->orderBy('created_at', 'DESC');
        }
        return $q;
    }

    public function storeEntry(EntryStructureDTO $entryStructure, $entry): int
    {
        // Set the entry params to be added
        $entry['uuid'] = $entryStructure->getEntryUuid();
        $entry['form_ref'] = $entryStructure->getFormRef();
        $entry['created_at'] = $entryStructure->getEntryCreatedAt();
        $entry['project_id'] = $entryStructure->getProjectId();
        $entry['device_id'] = $entryStructure->getHashedDeviceId();
        $entry['platform'] = $entryStructure->getPlatform();
        $entry['user_id'] = $entryStructure->getUserId();

        //Save entry to database
        try {
            $table = config('epicollect.tables.entries');
            if ($entryStructure->isBranch()) {
                $table = config('epicollect.tables.branch_entries');
            }
            return DB::table($table)->insertGetId($entry);
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            return 0;
        }
    }
}
