<?php

namespace ec5\Console\Commands;

use ec5\Services\System\SystemStatsExportService;
use Illuminate\Console\Command;
use Throwable;

class SystemStatsUploadCommand extends Command
{
    protected $signature = 'system:stats-upload ';
    protected $description = 'Manually trigger the Epicollect5 stats upload to the CGPS Dashboard S3 bucket';

    public function handle(SystemStatsExportService $service): int
    {
        $this->info('🚀 Initializing Stats Export...');

        try {
            $this->comment('Gathering data from system_stats and generating payload...');

            // Call the method we just finished
            $service->uploadToS3();

            $this->info('✅ Success! epicollect.json has been uploaded to S3.');

        } catch (Throwable $e) {
            $this->error('❌ Upload failed.');
            $this->line('Error: ' . $e->getMessage());

            // Optional: output the stack trace for deep debugging
            if ($this->confirm('Do you want to see the full stack trace?')) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }


}
