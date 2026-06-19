<?php

namespace Tests;

use ec5\Models\OAuth\OAuthClient;
use ec5\Models\User\User;

class CleanUpBefore extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->clearDatabase([]);

        // The dev .env's APP_URL gets baked into bootstrap/cache/config.php by
        // `php artisan config:cache` in after_pull-dev.sh. That cache poisons
        // route resolution in tests because Laravel loads the cache as a
        // static array and ignores .env.testing. Clear it so the test env
        // reads from .env.testing's APP_URL=http://localhost.
        if (file_exists(base_path('bootstrap/cache/config.php'))) {
            @unlink(base_path('bootstrap/cache/config.php'));
        }
    }

    public function test_database_is_cleared()
    {
        $this->assertEquals(
            0,
            User::where('email', 'like', '%@example.com')->count()
        );
    }

    /**
     * Fails if any oauth_clients.secret is not bcrypt-hashed.
     *
     * After Passport 13, client secrets must be hashed (via `artisan passport:hash`)
     * for the token endpoint to verify them. A plaintext secret causes
     * `Hash::check()` to throw "This password does not use the Bcrypt algorithm."
     * in `OAuthController::issueToken`, which surfaces as a 400 with the
     * exception logged.
     *
     * This guard runs in the pre-suite cleanup class, after `setUp()` has
     * already removed test data, so it only inspects the remaining (legit)
     * clients. If any of those are unhashed, the suite halts here with a
     * clear message pointing at the fix.
     */
    public function test_all_client_secrets_are_bcrypt_hashed()
    {
        $unhashed = OAuthClient::query()
            ->whereNotNull('secret')
            ->get()
            ->filter(fn ($c) => ! preg_match('/^\$2[ayb]/', $c->secret))
            ->map(fn ($c) => "client_id={$c->id} name={$c->name}")
            ->all();

        $this->assertEmpty(
            $unhashed,
            sprintf(
                "Found %d unhashed client secret(s). Run `php artisan passport:hash --force` to fix:%s  %s",
                count($unhashed),
                PHP_EOL,
                implode(PHP_EOL . '  ', $unhashed)
            )
        );
    }
}
