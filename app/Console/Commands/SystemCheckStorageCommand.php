<?php

namespace ec5\Console\Commands;

use ec5\Mail\ExceptionNotificationMail;
use Illuminate\Console\Command;
use DB;
use Illuminate\Support\Facades\App;
use Log;
use Mail;
use Throwable;

class SystemCheckStorageCommand extends Command
{
    protected int $thresholdGB = 0;

    protected array $adminEmails = [];

    //The command signature
    protected $signature = 'system:check-storage';

    //The console command description.
    protected $description = 'Check both the database and the files storage available';

    public function handle(): void
    {
        $this->thresholdGB = config('epicollect.setup.storage_available_min_threshold');
        $this->adminEmails = [
            config('epicollect.setup.super_admin_user.email'),
            config('epicollect.setup.system.email')
        ];

        // Get MySQL data directory
        $dataDir = $this->getMySQLDataDir();
        if ($dataDir) {
            // Check the available free space
            $freeSpaceGB = $this->getFreeDiskSpaceInGB($dataDir);
            if ($freeSpaceGB <= $this->thresholdGB) {
                $this->logAndAlert("MySQL database low disk space warning: Only {$freeSpaceGB} GB left.");
            } else {
                $this->logMessage("Sufficient MySQL disk space available: {$freeSpaceGB} GB.");
            }
        } else {
            $this->logError('Unable to determine MySQL data directory.');
        }

        $storagePath = storage_path();
        // Get the free disk space in bytes for the storage folder
        $freeSpaceGB = $this->getFreeDiskSpaceInGB($storagePath);
        //if less than threshold, alert admins
        if ($freeSpaceGB <= $this->thresholdGB) {
            $this->logAndAlert('Low storage, currently ' . $freeSpaceGB . ' GB.');
        } else {
            $this->logMessage("Sufficient storage disk space available: {$freeSpaceGB} GB.");
        }
    }

    /**
     * Get the MySQL data directory.
     */
    protected function getMySQLDataDir(): ?string
    {
        $result = DB::select("SHOW VARIABLES LIKE 'datadir'");
        return $result[0]->Value ?? null;
    }

    /**
     * Get free disk space in GB for the given path.
     */
    protected function getFreeDiskSpaceInGB(string $path): float
    {
        $freeSpaceBytes = disk_free_space($path);
        return round($freeSpaceBytes / 1024 / 1024 / 1024);
    }

    /**
     * Log message to terminal and Laravel log files.
     */
    protected function logMessage(string $message): void
    {
        // Log to terminal and log file
        $this->info($message); // Display in terminal
        Log::info($message); // Log to Laravel log file
    }

    /**
     * Log error to terminal and Laravel log files.
     */
    protected function logError(string $error): void
    {
        // Log error to terminal and log file
        $this->error($error); // Display error in terminal
        Log::error($error); // Log error to Laravel log file
    }

    /**
     * Log and send storage alert via email.
     */
    protected function logAndAlert(string $message): void
    {
        if (App::environment() === 'production') {
            try {
                Mail::to($this->adminEmails)
                    ->send(new ExceptionNotificationMail($message));
            } catch (Throwable $e) {
                $this->logError($e->getMessage());
            }
        } else {
            $this->logError($message);
        }
    }
}
