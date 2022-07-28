<?php

namespace ec5\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;

use DB;
use Carbon\Carbon;

class User extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    public function getTotal()
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('count(id) as users_total')])
            ->get();
    }

    public function getToday()
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('count(id) as users_total')])
            ->where($this->table . '.created_at', '>=', Carbon::today())
            ->get();
    }

    public function getWeek()
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('count(id) as users_total')])
            ->where($this->table . '.created_at', '>=', Carbon::now()->subWeeks(1))
            ->where($this->table . '.created_at', '<=', Carbon::now()->subDays(1))
            ->get();
    }

    public function getMonth()
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('count(id) as users_total')])
            ->where($this->table . '.created_at', '>=', Carbon::now()->startOfMonth())
            ->get();
    }

    public function getYear()
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('count(id) as users_total')])
            ->where($this->table . '.created_at', '>=', Carbon::now()->startOfYear())
            ->where($this->table . '.created_at', '<=', Carbon::now()->endOfYear())
            ->get();
    }

    public function yearByMonth()
    {
        //count in sql is faster than eloquent, use raw!
        return DB::table($this->table)
            ->select([DB::raw('MONTH(created_at) as month'), DB::raw('count(id) as users_total')])
            ->where($this->table . '.created_at', '>=', Carbon::now()->startOfYear())
            ->groupBy('month')
            ->get();
    }

    public function getStats()
    {
        $total = $this->getTotal();
        $today = $this->getToday();
        $week = $this->getWeek();
        $month = $this->getMonth();
        $year = $this->getYear();
        $yearByMonth = $this->yearByMonth();

        $distributionByMonth = [];

        foreach ($yearByMonth as $currentMonth) {
            array_push($distributionByMonth,
                array(
                    $this->getMonthName($currentMonth->month) => $currentMonth->users_total
                )
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

    private function getMonthName($monthNumber)
    {
        return date("M", mktime(0, 0, 0, $monthNumber, 1));
    }
}
