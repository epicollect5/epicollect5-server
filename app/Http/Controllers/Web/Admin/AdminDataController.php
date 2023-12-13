<?php

namespace ec5\Http\Controllers\Web\Admin;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\Controller;
use ec5\Models\Eloquent\System\SystemStats;


class AdminDataController extends Controller
{
    private $dailySystemStats;
    private $apiResponse;

    public function __construct(SystemStats $systemStats, ApiResponse $apiResponse)
    {
        $this->dailySystemStats = $systemStats;
        $this->dailySystemStats->initDailyStats();
        $this->apiResponse = $apiResponse;
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

        if (sizeof($stats['users']) === 0) {
            return $this->apiResponse->errorResponse(400, ['systems-stats' => 'ec5_356']);
        }

        return response()->apiResponse($stats);
    }

    public function getProjectsStats()
    {
        $stats = array(
            "id" => uniqid(),
            "type" => 'projects-stats',
            "projects" => $this->dailySystemStats->getProjectStats()
        );

        if (sizeof($stats['projects']) === 0) {
            return $this->apiResponse->errorResponse(400, ['systems-stats' => 'ec5_356']);
        }

        return response()->apiResponse($stats);
    }

    public function getEntriesStats()
    {
        $stats = array(
            "id" => uniqid(),
            "type" => 'entries-stats',
            "entries" => $this->dailySystemStats->getEntriesStats(),
            "branch_entries" => $this->dailySystemStats->getBranchEntriesStats()
        );

        if (sizeof($stats['entries']) === 0 && sizeOf($stats['branch_entries']) === 0) {
            return $this->apiResponse->errorResponse(400, ['systems-stats' => 'ec5_356']);
        }

        return response()->apiResponse($stats);
    }
}
