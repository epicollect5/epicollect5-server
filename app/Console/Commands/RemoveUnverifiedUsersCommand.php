<?php

namespace ec5\Console\Commands;

use Carbon\Carbon;
use DB;
use ec5\Models\User\User;
use ec5\Models\User\UserVerify;
use Exception;
use Illuminate\Console\Command;
use Log;
use PDOException;

class RemoveUnverifiedUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remove-unverified-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove unverified users';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /**
         * Get unverified and local users older than the settings (usually 7 days)
         */
        try {
            DB::beginTransaction();

            //remove unverified users
            User::where('state', config('epicollect.strings.user_state.unverified'))
                ->where('provider', config('epicollect.strings.providers.local'))
                ->whereDate('created_at', '<', Carbon::now()->subDays(config('auth.account_unverified.expire')))
                ->delete();

            //remove expired verification tokens (belonging to the removed users)
            UserVerify::whereDate('created_at', '<', Carbon::now()->subDays(config('auth.account_unverified.expire')))
                ->delete();

            DB::commit();
        } catch (PDOException $e) {
            Log::error('failed to remove unverified users', ['exception' => $e]);
            DB::rollBack();
        } catch (Exception $e) {
            Log::error('failed to remove unverified users', ['exception' => $e]);
            DB::rollBack();
        }
    }
}
