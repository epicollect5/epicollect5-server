<?php

namespace ec5\Console\Commands;

use Carbon\Carbon;
use DB;
use ec5\Models\User\User;
use ec5\Models\User\UserVerify;
use Illuminate\Console\Command;
use Log;
use Throwable;

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
     */
    public function handle(): void
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
        } catch (Throwable $e) {
            Log::error('failed to remove unverified users', ['exception' => $e]);
            try {
                DB::rollBack();
            } catch (Throwable $e) {
                Log::error('failed to rollback -> remove unverified users', ['exception' => $e]);
            }
        }
    }
}
