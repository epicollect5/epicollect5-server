<?php

namespace ec5\Console\Commands;

use Illuminate\Console\Command;
use DB;
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
        } catch (Exception $e) {
            $this->error('MYSQL is currently down!');
            Mail::raw('MYSQL is currently down!', function ($message) {
                $message->from(env('MAIL_FROM_ADDRESS'), 'Epicollect5');
                $message->to(env('SYSTEM_EMAIL'));
                $message->subject('MYSQL is down...');
            });
        }
    }
}
