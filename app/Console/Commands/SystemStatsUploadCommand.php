<?php

namespace ec5\Console\Commands;

use ec5\Services\System\SystemStatsExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SystemStatsUploadCommand extends Command
{
    protected $signature = 'system:stats-upload ';
    protected $description = 'Manually trigger the Epicollect5 stats upload to the CGPS Dashboard S3 bucket';

    public function handle(SystemStatsExportService $service): int
    {
        $host = request()->getHost() ?? ''; // Real-time HTTP host

        if ($host !== 'five.epicollect.net') {
            $this->info('⚠️ This command is intended to run in the production environment. Current host: ' . $host . '. Exiting.');
            return self::FAILURE;
        }

        if (config('epicollect.setup.cgps_dashboard_upload_enabled') !== true) {
            $this->info('⚠️ CGPS Dashboard upload is disabled in configuration. Exiting.');
            return self::FAILURE;
        }

        $this->info('🚀 Initializing Stats Export...');

        try {
            $this->comment('Gathering data from system_stats and generating payload...');

            // Call the method we just finished
            $service->uploadToS3();

            $this->info('✅ Success! epicollect.json has been uploaded to S3.');

        } catch (Throwable $e) {
            $this->error('❌ Upload failed.');
            $this->line('Error: ' . $e->getMessage());
            Log::error('system:stats-upload failed', ['exception' => $e->getMessage()]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
