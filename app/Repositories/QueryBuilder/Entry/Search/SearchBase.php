<?php

namespace ec5\Repositories\QueryBuilder\Entry\Search;

use DB;
use Illuminate\Database\Query\Builder;
use Carbon\Carbon;

abstract class SearchBase
{

    protected $errors;

    protected $table = '';

    /**
     * SearchBase constructor.
     */
    public function __construct()
    {
        DB::connection()->enableQueryLog();
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

        $search_two = (empty($options['search_two'])) ? null : $options['search_two'];
        $search_op = (empty($options['search_op'])) ? null : $options['search_op'];

        $q->whereRaw($sql . $inputRef . '"."answer"\')) = ?', [$search]);

        return $q;
    }

    /**
     * @param Builder $q
     * @param $options
     * @return Builder
     */
    protected function sortAndFilterEntries(Builder $q, $options)
    {
        // Filtering
        if (!empty($options['filter_by'])) {

            // Filter between
            if (!empty($options['filter_from']) && !empty($options['filter_to'])) {

                //create artificial dates using Carbon modifiers
                //to include the full range of entries
                $from = Carbon::parse($options['filter_from'])->startOfDay();
                $to = Carbon::parse($options['filter_to'])->endOfDay();

                $q->whereBetween($options['filter_by'], [$from, $to]);
            } // Filter from
            else {
                if (!empty($options['filter_from'])) {
                    $q->where($options['filter_by'], '>=', $options['filter_from']);
                } // Filter to
                else {
                    if (!empty($options['filter_to'])) {
                        $q->where($options['filter_by'], '<=', $options['filter_to']);
                    }
                }
            }
        }

        //filter by title
        if (!empty($options['title'])) {
            $q->where('title', 'LIKE', '%' . $options['title'] . '%');
        }

        // Sorting
        if (!empty($options['sort_by']) && !empty($options['sort_order'])) {
            if ($options['sort_by'] === 'title') {
                //handle the natural sort on alphanumeric titles -> t.ly/tl5X
                $q->orderByRaw('LENGTH(' . $options['sort_by'] . ') ' . $options['sort_order'] . ' , ' . $options['sort_by'] . ' ' . $options['sort_order']);
            } else {
                $q->orderBy($options['sort_by'], $options['sort_order']);
            }

        } else {
            //default sorting, most recent first
            $q->orderBy('created_at', 'DESC');
        }

        return $q;
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

    /**
     * @param $projectId
     * @param $options
     * @param array $columns
     * @return Builder
     */
    abstract public function getEntry($projectId, $options, $columns = array('*'));


}
