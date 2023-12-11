<?php

namespace ec5\Traits\Eloquent;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;

trait Entries
{
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
}
