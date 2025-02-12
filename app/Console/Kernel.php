<?php

namespace ec5\Console;

use ec5\Console\Commands\SystemCheckStorageCommand;
use ec5\Console\Commands\RemoveUnverifiedUsersCommand;
use ec5\Console\Commands\SeedEntriesCommand;
use ec5\Console\Commands\SeedMediaCommand;
use ec5\Console\Commands\SeedSuperadminCommand;
use ec5\Console\Commands\SystemClearOpcache;
use ec5\Console\Commands\SystemStatsCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\App;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     */
    protected $commands = [
        SystemStatsCommand::class,
        RemoveUnverifiedUsersCommand::class,
        SystemCheckStorageCommand::class,
        SystemCheckStorageCommand::class,
        SystemClearOpcache::class,
        SeedEntriesCommand::class,
        SeedMediaCommand::class,
        SeedSuperadminCommand::class
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        if (App::environment() === 'production') {
            //grab system stats for the current day
            $schedule->command('system:stats')
                ->dailyAt('01:00')
                ->timezone('Europe/London')
                ->withoutOverlapping();

            //check storage available
            $schedule->command('system:check-storage')
                ->dailyAt('07:00')
                ->timezone('Europe/London')
                ->withoutOverlapping();
        } else {
            //run commands every hour locally (for debugging)
            $schedule->command('system:stats')
                ->hourly()
                ->withoutOverlapping();

            $schedule->command('system:check-storage')
                ->hourly()
                ->withoutOverlapping();
        }
    }
}
