<?php

namespace ec5\Traits\Eloquent\System;

use Carbon\Carbon;
use DB;
use ec5\Libraries\Utilities\Common;
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

    public function toStatsArray(array $byMonth, array $byplatform, array $total, array $today, array $week, array $month, array $year): array
    {
        $distributionByMonth = [];
        foreach ($byMonth as $currentMonth) {
            $distributionByMonth[] = array(
                Common::getMonthName($currentMonth->month) => $currentMonth->entries_total
            );
        }

        $platforms = [];
        foreach ($byplatform as $platform) {
            //when platform name is empty, return unknown
            $platformName = $platform->platform === '' ? 'unknown' : strtolower($platform->platform);
            $platforms[$platformName] = $platform->total;
        }

        //add amazon-fireos entries to android, and remove amazon-fireos
        if (isset($platforms['amazon-fireos'])) {
            $platforms['android'] += $platforms['amazon-fireos'];
            unset($platforms['amazon-fireos']);
        }

        return array(
            "total" => array(
                "private" => count($total) > 0 ? $total[0]->entries_total : 0,
                "public" => count($total) > 1 ? $total[1]->entries_total : 0
            ),
            "today" => array(
                "private" => count($today) > 0 ? $today[0]->entries_total : 0,
                "public" => count($today) > 1 ? $today[1]->entries_total : 0
            ),
            "week" => array(
                "private" => count($week) > 0 ? $week[0]->entries_total : 0,
                "public" => count($week) > 1 ? $week[1]->entries_total : 0
            ),
            "month" => array(
                "private" => count($month) > 0 ? $month[0]->entries_total : 0,
                "public" => count($month) > 1 ? $month[1]->entries_total : 0
            ),
            "year" => array(
                "private" => count($year) > 0 ? $year[0]->entries_total : 0,
                "public" => count($year) > 1 ? $year[1]->entries_total : 0
            ),
            "by_month" => $distributionByMonth,
            "by_platform" => $platforms
        );
    }
}
