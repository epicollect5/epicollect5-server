<?php

namespace ec5\Models\System;

use ec5\Traits\Eloquent\System\EntriesStats;
use Illuminate\Database\Eloquent\Model;

class BranchEntriesTotals extends Model
{
    use EntriesStats;

    protected $table = 'branch_entries';
    public $timestamps = false;

    private function getTotal()
    {
        return $this->getEntriesTotal($this->table)->toArray();
    }

    private function getYesterday()
    {
        return $this->getEntriesYesterday($this->table)->toArray();
    }

    private function getLastWeek()
    {
        return $this->getEntriesLastWeek($this->table)->toArray();
    }

    private function getLastMonth()
    {
        return $this->getEntriesLastMonth($this->table)->toArray();
    }

    private function getLastYear()
    {
        return $this->getEntriesLastYear($this->table)->toArray();
    }

    public function getByMonth()
    {
        return $this->getEntriesByMonth($this->table)->toArray();
    }

    //get month name passing number
    private function getMonthName($monthNumber)
    {
        return date("M", mktime(0, 0, 0, $monthNumber, 1));
    }

    private function getByPlatform()
    {
        return $this->getEntriesByPlatform($this->table)->toArray();
    }

    public function getStats()
    {
        $total = $this->getTotal();
        $today = $this->getYesterday();
        $week = $this->getLastWeek();
        $month = $this->getLastMonth();
        $year = $this->getLastYear();
        $byMonth = $this->getByMonth();
        $byplatform = $this->getByPlatform();

        //convert $byMonth to associative array of "month=>total" pairs
        $distributionByMonth = [];
        foreach ($byMonth as $currentMonth) {
            array_push(
                $distributionByMonth,
                array(
                    $this->getMonthName($currentMonth->month) => $currentMonth->entries_total
                )
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
