<?php

namespace ec5\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

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
        Commands\CheckDatabase::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //grab system stats for the current day
        $schedule->command('system-stats')
            //->everyMinute()
            ->dailyAt('23:45')
            ->withoutOverlapping();

        // $schedule->command('check-database')->everyMinute();
    }
}
