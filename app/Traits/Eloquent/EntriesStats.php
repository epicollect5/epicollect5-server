<?php

namespace ec5\Traits\Eloquent;

use DB;
use Carbon\Carbon;

trait EntriesStats
{
    public function getEntriesTotal($table)
    {
        return DB::table($table)
            ->leftJoin('projects', 'projects.id', '=', $table . '.project_id')
            ->selectRaw('count(' . $table . '.id) as entries_total, projects.access')
            ->groupBy('projects.access')
            ->get();
    }

    public function getEntriesToday($table)
    {
        return DB::table($table)->leftJoin('projects', 'projects.id', '=', $table . '.project_id')
            ->selectRaw('count(' . $table . '.id) as entries_total, projects.access')
            ->where($table . '.created_at', '>=', Carbon::today())
            ->groupBy('projects.access')
            ->get();
    }

    public function getEntriesLastWeek($table)
    {
        return DB::table($table)->leftJoin('projects', 'projects.id', '=', $table . '.project_id')
            ->selectRaw('count(' . $table . '.id) as entries_total, projects.access')
            ->where($table . '.created_at', '>=', Carbon::now()->subWeeks(1))
            ->where($table . '.created_at', '<=', Carbon::now()->subDays(1))
            ->groupBy('projects.access')
            ->get();
    }

    public function getEntriesLastMonth($table)
    {
        return DB::table($table)->leftJoin('projects', 'projects.id', '=', $table . '.project_id')
            ->selectRaw('count(' . $table . '.id) as entries_total, projects.access')
            ->where($table . '.created_at', '>=', Carbon::now()->startOfMonth())
            ->groupBy('projects.access')
            ->get();
    }

    public function getEntriesLastYear($table)
    {
        return DB::table($table)->leftJoin('projects', 'projects.id', '=', $table . '.project_id')
            ->selectRaw('count(' . $table . '.id) as entries_total, projects.access')
            ->where($table . '.created_at', '>=', Carbon::now()->startOfYear())
            ->where($table . '.created_at', '<=', Carbon::now()->endOfYear())
            ->groupBy('projects.access')
            ->get();
    }

    public function getEntriesByMonth($table)
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($table)
            ->select([DB::raw('MONTH(created_at) as month'), DB::raw('count(id) as entries_total')])
            ->where($table . '.created_at', '>=', Carbon::now()->startOfYear())
            ->where($table . '.created_at', '<=', Carbon::now())
            ->groupBy('month')
            ->get();
    }

    public function getEntriesByPlatform($table)
    {
        return DB::table($table)
            ->selectRaw('count(' . $table . '.id) as total, platform')
            ->groupBy('platform')
            ->get();
    }
}
