<?php

namespace ec5\Console\Commands;

use Illuminate\Console\Command;

class SeedEntriesCommand extends Command
{
    protected $signature = 'seed:entries';
    protected $description = 'Seed any number of entries to a specific project';

    public function handle(): void
    {
        // Call the db:seed command, which will invoke EntriesSeeder
        $this->call('db:seed', [
            '--class' => 'EntriesSeeder',
        ]);
    }
}
