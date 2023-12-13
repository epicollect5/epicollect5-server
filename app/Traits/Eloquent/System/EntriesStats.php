<?php

namespace ec5\Traits\Eloquent\System;

use Carbon\Carbon;
use DB;
use Illuminate\Support\Collection;

trait EntriesStats
{
    public function getEntriesTotal($table): Collection
    {
        return DB::table($table)
            ->leftJoin('projects', 'projects.id', '=', $table . '.project_id')
            ->selectRaw('count(' . $table . '.id) as entries_total, projects.access')
            ->groupBy('projects.access')
            ->get();
    }

    public function getEntriesYesterday($table): Collection
    {
        return DB::table($table)->leftJoin('projects', 'projects.id', '=', $table . '.project_id')
            ->selectRaw('count(' . $table . '.id) as entries_total, projects.access')
            ->where($table . '.created_at', '>=', Carbon::yesterday())
            ->groupBy('projects.access')
            ->get();
    }

    public function getEntriesLastWeek($table): Collection
    {
        return DB::table($table)->leftJoin('projects', 'projects.id', '=', $table . '.project_id')
            ->selectRaw('count(' . $table . '.id) as entries_total, projects.access')
            ->where($table . '.created_at', '>=', Carbon::now()->subWeeks(1))
            ->where($table . '.created_at', '<=', Carbon::now()->subDays(1))
            ->groupBy('projects.access')
            ->get();
    }

    public function getEntriesLastMonth($table): Collection
    {
        return DB::table($table)->leftJoin('projects', 'projects.id', '=', $table . '.project_id')
            ->selectRaw('count(' . $table . '.id) as entries_total, projects.access')
            ->where($table . '.created_at', '>=', Carbon::now()->startOfMonth())
            ->groupBy('projects.access')
            ->get();
    }

    public function getEntriesLastYear($table): Collection
    {
        return DB::table($table)->leftJoin('projects', 'projects.id', '=', $table . '.project_id')
            ->selectRaw('count(' . $table . '.id) as entries_total, projects.access')
            ->where($table . '.created_at', '>=', Carbon::now()->startOfYear())
            ->where($table . '.created_at', '<=', Carbon::now()->endOfYear())
            ->groupBy('projects.access')
            ->get();
    }

    public function getEntriesByMonth($table): Collection
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($table)
            ->select([DB::raw('MONTH(created_at) as month'), DB::raw('count(id) as entries_total')])
            ->where($table . '.created_at', '>=', Carbon::now()->startOfYear())
            ->where($table . '.created_at', '<=', Carbon::now())
            ->groupBy('month')
            ->get();
    }

    public function getEntriesByPlatform($table): Collection
    {
        return DB::table($table)
            ->selectRaw('count(' . $table . '.id) as total, platform')
            ->groupBy('platform')
            ->get();
    }
}
