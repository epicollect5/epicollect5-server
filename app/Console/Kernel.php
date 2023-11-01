<?php

namespace ec5\Console;

use ec5\Console\Commands\CheckStorageAvailableDiskSpace;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\App;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\SystemStatsCommand::class,
        Commands\RemoveUnverifiedUsersCommand::class,
        Commands\CheckDatabase::class,
        CheckStorageAvailableDiskSpace::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        if (App::environment() === 'production') {
            //grab system stats for the current day
            $schedule->command('system-stats')
                ->dailyAt('01:00')
                ->timezone('Europe/London')
                ->withoutOverlapping();

            $schedule->command('check-storage-available')
                ->dailyAt('07:00')
                ->timezone('Europe/London')
                ->withoutOverlapping();
        } else {
            //run commands every hour locally (for debugging)
            $schedule->command('system-stats')
                ->hourly()
                ->withoutOverlapping();

            $schedule->command('check-storage-available')
                ->hourly()
                ->withoutOverlapping();
        }
        // $schedule->command('check-database')->everyMinute();
    }
}
