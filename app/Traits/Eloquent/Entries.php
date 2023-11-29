<?php

namespace ec5\Traits\Eloquent;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;

trait Entries
{
    public static function sortAndFilterEntries(Builder $q, $options): Builder
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
}
