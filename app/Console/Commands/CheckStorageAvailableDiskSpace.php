<?php

namespace ec5\Console\Commands;

use ec5\Mail\ExceptionNotificationMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckStorageAvailableDiskSpace extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check-storage-available';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the available storage for files';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $admins = [
            config('epicollect.setup.system.email'),
            config('epicollect.setup.super_admin_user.email')
        ];
        $storagePath = storage_path();
        // Get the free disk space in bytes for the storage folder
        $freeSpaceInBytes = disk_free_space($storagePath);
        // Convert the free space to gigabytes
        $freeSpaceInGB = (int)ceil($freeSpaceInBytes / 1024 / 1024 / 1024);
        //if less than 50GB, alert admins
        //todo: maybe we can increase a DO Volume via the API?
        if ($freeSpaceInGB <= config('epicollect.setup.storage_available_min_threshold')) {
            //send low storage notification to system admins
            if (App::environment() === 'production') {
                Mail::to($admins)
                    ->send(new ExceptionNotificationMail('Low storage, currently ' . $freeSpaceInGB . ' GB.'));
            }
        }

        if (App::environment() === 'local') {
            Log::info('Storage available ' . $freeSpaceInGB . ' GB.');
        }
    }
}
