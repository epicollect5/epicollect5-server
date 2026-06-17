<?php

namespace ec5\Providers;

use Carbon\Carbon;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'ec5\Model' => 'ec5\Policies\ModelPolicy',
    ];

    /**
     * Register any application authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Keep using integer IDs for OAuth clients (existing schema)
        Passport::$clientUuids = false;

        // Set the expiry time for Passport access tokens
        Passport::tokensExpireIn(Carbon::now()->addSeconds(config('auth.passport.expire')));

        // Passport 13 hashes client secrets by default (see vendor/laravel/passport/UPGRADE.md).
        // The `passport:hash` artisan command is a one-time migration run in after_pull-*.sh
        // to bcrypt-hash pre-existing plain-text rows in oauth_clients.secret; no opt-in
        // call (Passport::hashClientSecrets()) is required or available in Passport 13.
    }
}
