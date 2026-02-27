<?php

namespace ec5\Console;

use ec5\Console\Commands\RemoveUnverifiedUsersCommand;
use ec5\Console\Commands\SeedEntriesCommand;
use ec5\Console\Commands\SeedMediaCommand;
use ec5\Console\Commands\SeedSuperadminCommand;
use ec5\Console\Commands\SystemCheckStorageCommand;
use ec5\Console\Commands\SystemClearOpcache;
use ec5\Console\Commands\SystemProjectStorageCommand;
use ec5\Console\Commands\SystemStatsCommand;
use ec5\Console\Commands\SystemStatsUploadCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use jdavidbakr\LaravelCacheGarbageCollector\LaravelCacheGarbageCollector;
use Throwable;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     */
    protected $commands = [
        SystemStatsCommand::class,
        SystemStatsUploadCommand::class,
        RemoveUnverifiedUsersCommand::class,
        SystemCheckStorageCommand::class,
        SystemClearOpcache::class,
        SystemProjectStorageCommand::class,
        SeedEntriesCommand::class,
        SeedMediaCommand::class,
        SeedSuperadminCommand::class,
        LaravelCacheGarbageCollector::class
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
                ->timezone('UTC')
                ->withoutOverlapping()
                ->onSuccess(function () {
                    // Upload stats to S3 after successful collection
                    try {
                        Artisan::call('system:stats-upload');
                    } catch (Throwable $e) {
                        Log::error('system:stats-upload failed in scheduler', [
                            'error' => $e->getMessage()
                        ]);
                    }
                });

            //check storage available
            $schedule->command('system:check-storage')
                ->dailyAt('07:00')
                ->timezone('UTC')
                ->withoutOverlapping();

            //clear laravel expired cache files on Sunday at 04:00 UTC
            $schedule->command('cache:gc')
                ->weeklyOn(0, '04:00')
                ->timezone('UTC')
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
