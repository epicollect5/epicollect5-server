<?php

namespace ec5\Console\Commands;

use Database\Seeders\MediaSeeder;
use Illuminate\Console\Command;

class SeedMediaCommand extends Command
{
    protected $signature = 'seed:media';
    protected $description = 'Seed media files to a project';

    public function handle(): void
    {
        // Call the db:seed command, which will invoke EntriesSeeder
        $this->call('db:seed', [
            '--class' => MediaSeeder::class,
        ]);
    }
}
