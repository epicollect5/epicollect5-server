<?php

namespace ec5\Services\System;

use ec5\Traits\Eloquent\System\EntriesStats;

class EntriesTotalsService
{
    use EntriesStats;

    protected string $table = 'entries';

    /**
     * Return the total number of entries (split by private and public)
     */
    private function getTotal(): array
    {
        return $this->getEntriesTotal($this->table)->toArray();
    }

    /**
     * Return the total number of entries for current day (split by private and public)
     */
    private function getYesterday(): array
    {
        return $this->getEntriesYesterday($this->table)->toArray();
    }

    /**
     * Return the total number of entries fon the last 7 days from current day (split by private and public)
     */
    private function getLastWeek(): array
    {
        return $this->getEntriesLastWeek($this->table)->toArray();
    }

    /**
     * Return the total number of entries of the current month (split by private and public)
     */
    private function getLastMonth(): array
    {
        return $this->getEntriesLastMonth($this->table)->toArray();
    }

    /**
     * Return the total number of entries of current year (split by private and public)
     */
    private function getLastYear(): array
    {
        return $this->getEntriesLastYear($this->table)->toArray();
    }

    public function getByMonth(): array
    {
        return $this->getEntriesByMonth($this->table)->toArray();
    }

    private function getByPlatform(): array
    {
        return $this->getEntriesByPlatform($this->table)->toArray();
    }



    /**
     * Return object with all the stats about entries
     * sanitising missing values
     */
    public function getStats(): array
    {
        $total = $this->getTotal();
        $today = $this->getYesterday();
        $week = $this->getLastWeek();
        $month = $this->getLastMonth();
        $year = $this->getLastYear();
        $byplatform = $this->getByPlatform();
        $byMonth = $this->getByMonth();

        //convert $byMonth to associative array of "month=>total" pairs
        return $this->toStatsArray($byMonth, $byplatform, $total, $today, $week, $month, $year);
    }
}
