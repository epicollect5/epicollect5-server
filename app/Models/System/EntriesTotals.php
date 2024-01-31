<?php

namespace ec5\Models\System;

use ec5\Traits\Eloquent\System\EntriesStats;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Entry
 * @package ec5\Models\Eloquent
 */
class EntriesTotals extends Model
{
    use EntriesStats;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'entries';
    public $timestamps = false;

    /**
     * Return the total number of entries (split by private and public)
     * @return mixed
     */
    private function getTotal()
    {
        return $this->getEntriesTotal($this->table)->toArray();
    }

    /**
     * Return the total number of entries for current day (split by private and public)
     * @return mixed
     */
    private function getYesterday()
    {
        return $this->getEntriesYesterday($this->table)->toArray();
    }

    /**
     * Return the total number of entries fon the last 7 days from current day (split by private and public)
     * @return mixed
     */
    private function getLastWeek()
    {
        return $this->getEntriesLastWeek($this->table)->toArray();
    }

    /**
     * Return the total number of entries of the current month (split by private and public)
     * @return mixed
     */
    private function getLastMonth()
    {
        return $this->getEntriesLastMonth($this->table)->toArray();
    }

    /**
     * Return the total number of entries of current year (split by private and public)
     * @return mixed
     */
    private function getLastYear()
    {
        return $this->getEntriesLastYear($this->table)->toArray();
    }

    public function getByMonth()
    {
        return $this->getEntriesByMonth($this->table)->toArray();
    }

    private function getByPlatform()
    {
        return $this->getEntriesByPlatform($this->table)->toArray();
    }

    //get month name passing number
    private function getMonthName($monthNumber)
    {
        return date("M", mktime(0, 0, 0, $monthNumber, 1));
    }

    /**
     * Return object with all the stats about entries
     * sanitising missing values
     * @return mixed
     */
    public function getStats()
    {
        $total = $this->getTotal();
        $today = $this->getYesterday();
        $week = $this->getLastWeek();
        $month = $this->getLastMonth();
        $year = $this->getLastYear();
        $byplatform = $this->getByPlatform();
        $byMonth = $this->getByMonth();

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
