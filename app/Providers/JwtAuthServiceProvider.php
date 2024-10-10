<?php

namespace ec5\Providers;

use Auth;
use ec5\Libraries\Auth\Jwt\Jwt;
use ec5\Libraries\Auth\Jwt\JwtGuard;
use ec5\Libraries\Auth\Jwt\JwtUserProvider;
use ec5\Models\User\User;
use Illuminate\Support\ServiceProvider;

/**
 * Extend the Auth Guard service by adding a 'jwt' driver
 */
class JwtAuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application authentication / authorization services.
     */
    public function boot(): void
    {
        Auth::provider('jwt', function ($app) {
            return new JwtUserProvider($app['hash'], new User());
        });

        Auth::extend('jwt', function ($app) {
            // Create new Jwt User provider
            $userProvider = new JwtUserProvider($app['hash'], new User());
            // Pass this to the Jwt Guard
            $guard = new JwtGuard($userProvider, $app['request'], new Jwt());
            // Set cookie jar
            if (method_exists($guard, 'setCookieJar')) {
                $guard->setCookieJar($app['cookie']);
            }
            // Refresh request
            if (method_exists($guard, 'setRequest')) {
                $guard->setRequest($app->refresh('request', $guard, 'setRequest'));
            }
            return $guard;
        });
    }

    public function register()
    {
        //
    }
}
