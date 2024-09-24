<?php

namespace ec5\Console\Commands;

use ec5\Models\System\SystemStats as SystemStatsModel;
use ec5\Services\System\BranchEntriesTotalsService;
use ec5\Services\System\EntriesTotalsService;
use ec5\Services\System\ProjectsTotalsService;
use ec5\Services\System\UsersTotalsService;
use Illuminate\Console\Command;
use Log;
use Throwable;

class SystemStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     */
    protected $signature = 'system:stats';

    /**
     * The console command description.
     *
     */
    protected $description = 'Query system stats (users, projects, entries)';

    /**
     * Execute the console command.
     *
     */
    public function handle(): void
    {
        $systemStatsModel = new SystemStatsModel();
        $usersTotals = new UsersTotalsService();
        $projectsTotals = new ProjectsTotalsService();
        $entry = new EntriesTotalsService();
        $branchEntry = new BranchEntriesTotalsService();

        //get the daily stats and save it to the db
        try {
            $entriesStats = $entry->getStats();
            $branchEntriesStats = $branchEntry->getStats();
            $userStats = $usersTotals->getStats();
            $projectStats = $projectsTotals->getStats();

            $systemStatsModel->user_stats = json_encode($userStats);
            $systemStatsModel->project_stats = json_encode($projectStats);
            $systemStatsModel->entries_stats = json_encode($entriesStats);
            $systemStatsModel->branch_entries_stats = json_encode($branchEntriesStats);

            $systemStatsModel->save();
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
        }
    }
}
