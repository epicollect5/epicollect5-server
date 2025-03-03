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
    /** Get GeoJSON data for entries or branch entries
     *
     * @param $projectId
     * @param $params
     * @return Builder
     */
    public function getGeoJsonData($projectId, $params): Builder
    {
        $selectSql = 'JSON_EXTRACT(geo_json_data, ?) as geo_json_data ';
        $whereSql = 'project_id = ?';

        /**
         * Get the GeoJSON data for a location question by passing the input reference (input_ref) of that question.
         *
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
         *
         */
        $q = DB::table($this->table)
            ->whereRaw($whereSql, [$projectId])
            ->selectRaw($selectSql, ['$."' . $params['input_ref'] . '"']);

        /**
         * filter by user (imp: applied to COLLECTOR ROLE ONLY)
         *
         * @see EntriesViewService::getSanitizedQueryParams
         */
        if (!empty($params['user_id'])) {
            $q->where('user_id', '=', $params['user_id']);
        }

        return self::sortAndFilterEntries($q, $params);
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

    public static function sortAndFilterEntries(Builder $q, $filters): Builder
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
