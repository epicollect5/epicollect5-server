<?php

namespace Tests\Database\Migrations;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Covers database/migrations/2026_06_19_000002_drop_unused_oauth_tables.php
 *
 * The migration drops three Passport tables backing OAuth grant types this
 * app has never enabled: oauth_personal_access_clients (Passport 13
 * UPGRADE.md line 116-124 explicitly blesses this drop), oauth_auth_codes
 * (authorization_code grant), and oauth_refresh_tokens (refresh_token
 * grant). Each test owns the table state directly because DDL operations
 * implicitly commit in MySQL, which breaks the DatabaseTransactions trait's
 * rollback guarantee.
 */
class DropUnusedOauthTablesTest extends TestCase
{
    use DatabaseTransactions;

    private const string MIGRATION_PATH = 'migrations/2026_06_19_000002_drop_unused_oauth_tables.php';

    private const array FORBIDDEN_PATTERNS = [
        'createAuthorizationCodeGrantClient',
        'createPasswordGrantClient',
        'createImplicitGrantClient',
        'createDeviceAuthorizationGrantClient',
        'createPersonalAccessGrantClient',
        'enablePasswordGrant',
        'enableImplicitGrant',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the three tables exist before each test so the test can
        // drop them. The migration's down() is idempotent — it only
        // recreates missing tables.
        $this->recreateTables();
    }

    protected function tearDown(): void
    {
        // Recreate the tables so the test environment is back in standard
        // state for the next test.
        $this->recreateTables();

        parent::tearDown();
    }

    private function recreateTables(): void
    {
        (require database_path(self::MIGRATION_PATH))->down();
    }

    public function test_migration_drops_all_three_tables(): void
    {
        $this->assertTrue(Schema::hasTable('oauth_personal_access_clients'));
        $this->assertTrue(Schema::hasTable('oauth_auth_codes'));
        $this->assertTrue(Schema::hasTable('oauth_refresh_tokens'));

        (require database_path(self::MIGRATION_PATH))->up();

        $this->assertFalse(Schema::hasTable('oauth_personal_access_clients'));
        $this->assertFalse(Schema::hasTable('oauth_auth_codes'));
        $this->assertFalse(Schema::hasTable('oauth_refresh_tokens'));
    }

    public function test_migration_drops_tables_with_data_without_error(): void
    {
        // Pre-populate each table to confirm the drop succeeds even when
        // the tables contain rows. Production had 1 row in
        // oauth_personal_access_clients; this exercises that case.
        DB::table('oauth_personal_access_clients')->insert([
            'id' => 1,
            'client_id' => 1,
            'created_at' => '2017-06-21 10:54:38',
            'updated_at' => '2017-06-21 10:54:38',
        ]);
        DB::table('oauth_auth_codes')->insert([
            'id' => 'test-code',
            'user_id' => 1,
            'client_id' => 1,
            'scopes' => null,
            'revoked' => 0,
            'expires_at' => null,
        ]);
        DB::table('oauth_refresh_tokens')->insert([
            'id' => 'test-refresh',
            'access_token_id' => 'test-access',
            'revoked' => 0,
            'expires_at' => null,
        ]);

        (require database_path(self::MIGRATION_PATH))->up();

        $this->assertFalse(Schema::hasTable('oauth_personal_access_clients'));
        $this->assertFalse(Schema::hasTable('oauth_auth_codes'));
        $this->assertFalse(Schema::hasTable('oauth_refresh_tokens'));
    }

    public function test_migration_is_reversible(): void
    {
        $migration = require database_path(self::MIGRATION_PATH);

        $migration->up();
        $this->assertFalse(Schema::hasTable('oauth_personal_access_clients'));
        $this->assertFalse(Schema::hasTable('oauth_auth_codes'));
        $this->assertFalse(Schema::hasTable('oauth_refresh_tokens'));

        $migration->down();
        $this->assertTrue(Schema::hasTable('oauth_personal_access_clients'));
        $this->assertTrue(Schema::hasTable('oauth_auth_codes'));
        $this->assertTrue(Schema::hasTable('oauth_refresh_tokens'));
    }

    public function test_app_oauth_flow_uses_no_dropped_grants(): void
    {
        // Defense-in-depth: if a future developer adds a call to one of
        // these forbidden grant factories without first recreating the
        // backing table, this test fails and forces the deliberate schema
        // change. Static check via PHP — no shell exec, portable across OS.
        $directories = [
            app_path(),
            base_path('config'),
            base_path('routes'),
        ];

        $violations = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                foreach (self::FORBIDDEN_PATTERNS as $pattern) {
                    if (str_contains($contents, $pattern)) {
                        $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                        $violations[] = "{$relative}: contains '{$pattern}'";
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Dropped grant types must not be used without first recreating the backing tables:\n  "
                . implode("\n  ", $violations)
        );
    }
}
