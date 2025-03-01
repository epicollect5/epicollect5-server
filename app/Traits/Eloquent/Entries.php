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
