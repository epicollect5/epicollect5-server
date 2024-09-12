<?php

namespace ec5\Services\System;

use ec5\Traits\Eloquent\System\EntriesStats;

class BranchEntriesTotalsService
{
    use EntriesStats;

    protected string $table = 'branch_entries';

    private function getTotal(): array
    {
        return $this->getEntriesTotal($this->table)->toArray();
    }

    private function getYesterday(): array
    {
        return $this->getEntriesYesterday($this->table)->toArray();
    }

    private function getLastWeek(): array
    {
        return $this->getEntriesLastWeek($this->table)->toArray();
    }

    private function getLastMonth(): array
    {
        return $this->getEntriesLastMonth($this->table)->toArray();
    }

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

    public function getStats(): array
    {
        $total = $this->getTotal();
        $today = $this->getYesterday();
        $week = $this->getLastWeek();
        $month = $this->getLastMonth();
        $year = $this->getLastYear();
        $byMonth = $this->getByMonth();
        $byplatform = $this->getByPlatform();

        //convert $byMonth to associative array of "month=>total" pairs
        return $this->toStatsArray($byMonth, $byplatform, $total, $today, $week, $month, $year);
    }
}
