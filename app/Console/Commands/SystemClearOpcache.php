<?php

namespace ec5\Console\Commands;

use Illuminate\Console\Command;

class SystemClearOpcache extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'system:clear-opcache';

    /**
     * The console command description.
     */
    protected $description = 'Clear Opcache';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $this->info('INFO   OPcache has been cleared.');
        } else {
            $this->error('OPcache is not enabled or the function is unavailable.');
        }
    }
}
