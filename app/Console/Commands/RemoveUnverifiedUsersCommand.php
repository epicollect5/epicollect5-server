<?php

namespace ec5\Console\Commands;

use Illuminate\Console\Command;

use ec5\Models\Eloquent\User;
use ec5\Models\Eloquent\UserVerify;
use Config;
use Exception;
use PDOException;
use Log;
use Carbon\Carbon;
use DB;

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
            User::where('state', Config::get('ec5Strings.user_state.unverified'))
                ->where('provider', Config::get('ec5Strings.providers.local'))
                ->whereDate('created_at', '<', Carbon::now()->subDays(env('ACCOUNT_UNVERIFIED_EXPIRES_IN')))
                ->delete();

            //remove expired verification tokens (belonging to the removed users)
            UserVerify::whereDate('created_at', '<', Carbon::now()->subDays(env('ACCOUNT_UNVERIFIED_EXPIRES_IN')))
                ->delete();

            DB::commit();
        }
        catch (PDOException $e) {
            Log::error('failed to remove unverified users', ['exception' => $e]);
            DB::rollBack();
        }
        catch (Exception $e) {
            Log::error('failed to remove unverified users', ['exception' => $e]);
            DB::rollBack();
        }
    }
}
