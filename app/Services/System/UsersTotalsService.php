<?php

namespace ec5\Services\System;

use Carbon\Carbon;
use DB;
use ec5\Libraries\Utilities\Common;
use Illuminate\Support\Collection;

class UsersTotalsService
{
    protected string $table = 'users';

    public function getTotal(): Collection
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('count(id) as users_total')])
            ->get();
    }

    public function getYesterday(): Collection
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('count(id) as users_total')])
            ->where($this->table . '.created_at', '>=', Carbon::yesterday())
            ->get();
    }

    public function getWeek(): Collection
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('count(id) as users_total')])
            ->where($this->table . '.created_at', '>=', Carbon::now()->subWeeks(1))
            ->where($this->table . '.created_at', '<=', Carbon::now()->subDays(1))
            ->get();
    }

    public function getMonth(): Collection
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('count(id) as users_total')])
            ->where($this->table . '.created_at', '>=', Carbon::now()->startOfMonth())
            ->get();
    }

    public function getYear(): Collection
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('count(id) as users_total')])
            ->where($this->table . '.created_at', '>=', Carbon::now()->startOfYear())
            ->where($this->table . '.created_at', '<=', Carbon::now()->endOfYear())
            ->get();
    }

    public function yearByMonth(): Collection
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('MONTH(created_at) as month'), DB::raw('count(id) as users_total')])
            ->where($this->table . '.created_at', '>=', Carbon::now()->startOfYear())
            ->groupBy('month')
            ->get();
    }

    public function getStats(): array
    {
        $total = $this->getTotal();
        $today = $this->getYesterday();
        $week = $this->getWeek();
        $month = $this->getMonth();
        $year = $this->getYear();
        $yearByMonth = $this->yearByMonth();

        $distributionByMonth = [];

        foreach ($yearByMonth as $currentMonth) {
            $distributionByMonth[] = array(
                Common::getMonthName($currentMonth->month) => $currentMonth->users_total
            );
        }

        return array(
            "total" => $total[0]->users_total,
            "today" => $today[0]->users_total,
            "week" => $week[0]->users_total,
            "month" => $month[0]->users_total,
            "year" => $year[0]->users_total,
            "by_month" => $distributionByMonth
        );
    }
}
