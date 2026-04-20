<?php

namespace ec5\Traits\Eloquent\System;

use Carbon\Carbon;
use DB;
use ec5\Libraries\Utilities\Common;
use Illuminate\Support\Collection;

trait EntriesStats
{
    public function getEntriesTotalFromProjectStats(): Collection
    {
        $projectsTable = config('epicollect.tables.projects');
        $projectStatsTable = config('epicollect.tables.project_stats');

        return DB::table($projectStatsTable)
            ->join($projectsTable, $projectsTable . '.id', '=', $projectStatsTable . '.project_id')
            ->selectRaw("COALESCE(SUM($projectStatsTable.total_entries), 0) as entries_total, $projectsTable.access")
            ->groupBy($projectsTable . '.access')
            ->orderBy($projectsTable . '.access')
            ->get();
    }

    public function getBranchEntriesTotalFromProjectStats(): Collection
    {
        $projectsTable = config('epicollect.tables.projects');
        $projectStatsTable = config('epicollect.tables.project_stats');

        return DB::table($projectStatsTable)
            ->join($projectsTable, $projectsTable . '.id', '=', $projectStatsTable . '.project_id')
            ->leftJoin(
                DB::raw(
                    "JSON_TABLE(COALESCE($projectStatsTable.branch_counts, JSON_OBJECT()), '$.*' " .
                    "COLUMNS (branch_entries_total INT PATH '$.count')) as branch_entries_stats"
                ),
                DB::raw('1'),
                '=',
                DB::raw('1')
            )
            ->selectRaw(
                "COALESCE(SUM(branch_entries_stats.branch_entries_total), 0) as entries_total, $projectsTable.access"
            )
            ->groupBy($projectsTable . '.access')
            ->orderBy($projectsTable . '.access')
            ->get();
    }

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

    private function mapEntriesTotalByAccess(array $items): array
    {
        $totals = [
            'private' => 0,
            'public' => 0
        ];

        foreach ($items as $item) {
            if (isset($item->access, $totals[$item->access])) {
                $totals[$item->access] = (int)$item->entries_total;
            }
        }

        return $totals;
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

        $totalByAccess = $this->mapEntriesTotalByAccess($total);
        $todayByAccess = $this->mapEntriesTotalByAccess($today);
        $weekByAccess = $this->mapEntriesTotalByAccess($week);
        $monthByAccess = $this->mapEntriesTotalByAccess($month);
        $yearByAccess = $this->mapEntriesTotalByAccess($year);

        return array(
            "total" => array(
                "private" => $totalByAccess['private'],
                "public" => $totalByAccess['public']
            ),
            "today" => array(
                "private" => $todayByAccess['private'],
                "public" => $todayByAccess['public']
            ),
            "week" => array(
                "private" => $weekByAccess['private'],
                "public" => $weekByAccess['public']
            ),
            "month" => array(
                "private" => $monthByAccess['private'],
                "public" => $monthByAccess['public']
            ),
            "year" => array(
                "private" => $yearByAccess['private'],
                "public" => $yearByAccess['public']
            ),
            "by_month" => $distributionByMonth,
            "by_platform" => $platforms
        );
    }
}
