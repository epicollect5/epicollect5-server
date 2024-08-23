<?php

namespace ec5\Http\Controllers\Web\Admin;

use ec5\Http\Controllers\Controller;
use ec5\Models\System\SystemStats;
use Response;

class AdminDataController extends Controller
{
    private SystemStats $dailySystemStats;

    public function __construct(SystemStats $systemStats)
    {
        $this->dailySystemStats = $systemStats;
        $this->dailySystemStats->initDailyStats();
    }

    public function show()
    {
        return view('admin.tabs.stats');
    }

    public function getUsersStats()
    {
        $stats = array(
            "id" => uniqid(),
            "type" => 'users-stats',
            "users" => $this->dailySystemStats->getUserStats()
        );

        if (sizeof((array)$stats['users']) === 0) {
            return Response::apiErrorCode(400, ['systems-stats' => 'ec5_356']);
        }

        return Response::apiData($stats);
    }

    public function getProjectsStats()
    {
        $stats = array(
            "id" => uniqid(),
            "type" => 'projects-stats',
            "projects" => $this->dailySystemStats->getProjectStats()
        );


        if (sizeof((array)$stats['projects']) === 0) {
            return Response::apiErrorCode(400, ['systems-stats' => 'ec5_356']);
        }

        return Response::apiData($stats);
    }

    public function getEntriesStats()
    {
        $stats = array(
            "id" => uniqid(),
            "type" => 'entries-stats',
            "entries" => $this->dailySystemStats->getEntriesStats(),
            "branch_entries" => $this->dailySystemStats->getBranchEntriesStats()
        );

        if (sizeof((array)$stats['entries']) === 0 && sizeOf((array)$stats['branch_entries']) === 0) {
            return Response::apiErrorCode(400, ['systems-stats' => 'ec5_356']);
        }

        return Response::apiData($stats);
    }
}
