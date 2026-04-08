<?php

namespace ec5\Console\Commands;

use ec5\Services\Home\GenerateHomePageCacheService;
use Illuminate\Console\Command;
use Throwable;

class GenerateHomePageCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'system:cache-homepage';

    /**
     * The console command description.
     */
    protected $description = 'Generate and cache the home page featured projects content with embedded logos';

    private GenerateHomePageCacheService $cacheService;

    public function __construct(GenerateHomePageCacheService $cacheService)
    {
        parent::__construct();
        $this->cacheService = $cacheService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generating home page cache...');

        try {
            $success = $this->cacheService->generate();

            if ($success) {
                $this->info('✓ Home page cache generated successfully.');
                return 0;
            } else {
                $this->error('✗ Failed to generate home page cache. Check logs for details.');
                return 1;
            }
        } catch (Throwable $e) {
            $this->error('✗ Command failed: ' . $e->getMessage());
            return 1;
        }
    }
}
