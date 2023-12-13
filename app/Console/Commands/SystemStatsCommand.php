<?php

namespace ec5\Console\Commands;

use ec5\Models\Eloquent\System\BranchEntriesTotals;
use ec5\Models\Eloquent\System\EntriesTotals;
use ec5\Models\Eloquent\System\ProjectsTotals;
use ec5\Models\Eloquent\System\SystemStats as SystemStatsModel;
use ec5\Models\Eloquent\System\UsersTotals;
use Illuminate\Console\Command;

class SystemStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Query system stats (users, projects, entries)';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $systemStatsModel = new SystemStatsModel();
        $usersTotals = new UsersTotals();
        $projectsTotals = new ProjectsTotals();
        $entry = new EntriesTotals();
        $branchEntry = new BranchEntriesTotals();

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
        } catch (\Exception $e) {
            \Log::error('Failed to fetch system stats', ['exception' => $e]);
        }
    }
}
