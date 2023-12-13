<?php

namespace ec5\Models\Eloquent\System;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Log;

class SystemStats extends Model
{
    //
    protected $table = 'system_stats';
    public $timestamps = false;
    private $dailyStats;

    //get daily stats to initialise the model
    public function initDailyStats()
    {
        try {
            $this->dailyStats = $this->latest()->first();
        } catch (Exception $e) {
            Log::error('Failed init system stats', ['exception' => $e]);
            $this->dailyStats = [];
        }
    }

    //return user stats
    public function getUserStats()
    {
        if (isset($this->dailyStats)) {
            return json_decode($this->dailyStats['user_stats']);
        }
        return [];
    }

    //return project stats
    public function getProjectStats()
    {
        if (isset($this->dailyStats)) {
            return json_decode($this->dailyStats['project_stats']);
        }
        return [];
    }

    //return entries stats
    public function getEntriesStats()
    {
        if (isset($this->dailyStats)) {
            return json_decode($this->dailyStats['entries_stats']);
        }
        return [];
    }

    //return branch entries stats
    public function getBranchEntriesStats()
    {
        if (isset($this->dailyStats)) {
            return json_decode($this->dailyStats['branch_entries_stats']);
        }
        return [];
    }
}
