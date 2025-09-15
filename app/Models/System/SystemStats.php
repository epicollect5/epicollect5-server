<?php

namespace ec5\Models\System;

use ec5\Traits\Models\SerializeDates;
use Illuminate\Database\Eloquent\Model;
use Log;
use Throwable;

class SystemStats extends Model
{
    /**
     * @property int $id
     * @property string|null $user_stats
     * @property string|null $project_stats
     * @property string|null $entries_stats
     * @property string|null $branch_entries_stats
     * @property string $created_at
     */

    use SerializeDates;

    protected $table = 'system_stats';
    public $timestamps = false;
    private mixed $dailyStats;

    //get daily stats to initialise the model
    public function initDailyStats(): void
    {
        try {
            $this->dailyStats = $this->latest()->first();
        } catch (Throwable $e) {
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
