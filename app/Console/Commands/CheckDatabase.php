<?php

namespace ec5\Console\Commands;

use Illuminate\Console\Command;
use DB;
use Illuminate\Support\Facades\Config;
use Mail;
use Exception;

class CheckDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check-database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the database connection';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Test database connection
        try {
            DB::connection()->getPdo();
            $this->info('Database is running');
        } catch (\Throwable $e) {
            $this->error('MYSQL is currently down!');
            Mail::raw('MYSQL is currently down!', function ($message) {
                $message->from(config('mail.from.address'), config('mail.from.name'));
                $message->to(config('epicollect.setup.system.email'));
                $message->subject('MYSQL is down...');
            });
        }
    }
}
