<?php

namespace ec5\Console\Commands;

use ec5\Models\Eloquent\ProjectsTotals;
use Illuminate\Console\Command;

use ec5\Models\Eloquent\User;
use ec5\Models\Eloquent\Entry;
use ec5\Models\Eloquent\BranchEntry;
use ec5\Models\Eloquent\SystemStats as SystemStatsModel;

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
        $user = new User();
        $project = new ProjectsTotals();
        $entry = new Entry();
        $branchEntry = new BranchEntry();

        //get the daily stats and save to the db
        try {
            $entriesStats = $entry->getStats();
            $branchEntriesStats = $branchEntry->getStats();
            $userStats = $user->getStats();
            $projectStats = $project->getStats();

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
