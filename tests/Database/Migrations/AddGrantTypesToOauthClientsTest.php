<?php

namespace Tests\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Client as PassportClient;
use Tests\TestCase;

/**
 * Covers database/migrations/2026_06_19_000001_add_grant_types_to_oauth_clients.php
 *
 * The migration adds Passport 13's required `grant_types` and `redirect_uris`
 * columns to the legacy `oauth_clients` schema, backfills existing rows, and
 * tightens the columns to NOT NULL as a defense-in-depth measure. The bug it
 * fixes: Passport 13's Bridge\ClientRepository::fromClientModel
 * (vendor/laravel/passport/src/Bridge/ClientRepository.php:55-65) reads
 * `$model->grant_types` unconditionally; on the pre-13 schema this column
 * doesn't exist, so `$model->grant_types` is null and
 * `Client::hasGrantType('client_credentials')` returns false — every legacy
 * client fails /oauth/token with "unsupported_grant_type".
 */
class AddGrantTypesToOauthClientsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Always start from a known state. Drop any rows left over from
        // previous test runs (cleanDatabase in CleanUpBefore only deletes
        // clients linked to test projects, so orphan rows from migration
        // tests can leak), and drop the new columns so the migration under
        // test is what adds them.
        DB::table('oauth_clients')->delete();

        if (Schema::hasColumn('oauth_clients', 'grant_types')) {
            Schema::table('oauth_clients', function (Blueprint $table) {
                $table->dropColumn(['grant_types', 'redirect_uris']);
            });
        }
    }

    protected function tearDown(): void
    {
        // Schema changes are DDL — MySQL implicitly commits on DDL, which
        // breaks the DatabaseTransactions trait's rollback guarantee. Truncate
        // the table explicitly so data from this test doesn't leak into the
        // next, and drop the new columns so the next test starts from a clean
        // pre-migration state.
        DB::table('oauth_clients')->delete();

        if (Schema::hasColumn('oauth_clients', 'grant_types')) {
            Schema::table('oauth_clients', function (Blueprint $table) {
                $table->dropColumn(['grant_types', 'redirect_uris']);
            });
        }

        parent::tearDown();
    }

    public function test_migration_adds_columns_and_backfills_existing_rows(): void
    {
        // Pre-condition: pre-migration state. Seed two rows in the legacy shape
        // (no grant_types, no redirect_uris; personal_access_client and
        // password_client are 0, which is what the old boolean fallback in
        // ClientRepository::create() wrote for client_credentials clients).
        DB::table('oauth_clients')->insert([
            [
                'name' => 'Legacy Client A',
                'secret' => 'secret-a',
                'provider' => null,
                'redirect' => '',
                'personal_access_client' => 0,
                'password_client' => 0,
                'revoked' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Legacy Client B',
                'secret' => 'secret-b',
                'provider' => null,
                'redirect' => '',
                'personal_access_client' => 0,
                'password_client' => 0,
                'revoked' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        require_once database_path('migrations/2026_06_19_000001_add_grant_types_to_oauth_clients.php');
        (new (require database_path('migrations/2026_06_19_000001_add_grant_types_to_oauth_clients.php')))->up();

        $this->assertTrue(Schema::hasColumn('oauth_clients', 'grant_types'));
        $this->assertTrue(Schema::hasColumn('oauth_clients', 'redirect_uris'));

        $rows = DB::table('oauth_clients')
            ->whereIn('name', ['Legacy Client A', 'Legacy Client B'])
            ->get();

        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame(['client_credentials'], json_decode($row->grant_types, true));
            $this->assertSame([], json_decode($row->redirect_uris, true));
        }
    }

    public function test_columns_are_not_null_after_migration(): void
    {
        require_once database_path('migrations/2026_06_19_000001_add_grant_types_to_oauth_clients.php');
        (new (require database_path('migrations/2026_06_19_000001_add_grant_types_to_oauth_clients.php')))->up();

        // Inspect column metadata directly. Relying on an INSERT to fail at
        // runtime would couple this test to MySQL sql_mode (strict vs. lenient
        // — lenient coerces NULL to '' silently). Verifying the schema state
        // is what we actually care about and is portable across MySQL modes.
        $columns = DB::select("SHOW COLUMNS FROM oauth_clients WHERE Field IN ('grant_types', 'redirect_uris')");
        $byName = collect($columns)->keyBy('Field');

        $this->assertTrue($byName->has('grant_types'));
        $this->assertTrue($byName->has('redirect_uris'));
        $this->assertSame('NO', $byName['grant_types']->Null, 'grant_types should be NOT NULL');
        $this->assertSame('NO', $byName['redirect_uris']->Null, 'redirect_uris should be NOT NULL');
    }

    public function test_migration_is_reversible(): void
    {
        require_once database_path('migrations/2026_06_19_000001_add_grant_types_to_oauth_clients.php');
        $migration = new (require database_path('migrations/2026_06_19_000001_add_grant_types_to_oauth_clients.php'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('oauth_clients', 'grant_types'));
        $this->assertTrue(Schema::hasColumn('oauth_clients', 'redirect_uris'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('oauth_clients', 'grant_types'));
        $this->assertFalse(Schema::hasColumn('oauth_clients', 'redirect_uris'));
    }

    public function test_legacy_client_passes_has_grant_type_check_after_migration(): void
    {
        // Regression test for the actual production bug. Pre-migration, a
        // legacy client would authenticate then fail at /oauth/token with
        // "unsupported_grant_type" because Client::hasGrantType() returned
        // false on null. Post-migration, hasGrantType() must return true.
        DB::table('oauth_clients')->insert([
            'name' => 'Regression Client',
            'secret' => 'secret',
            'provider' => null,
            'redirect' => '',
            'personal_access_client' => 0,
            'password_client' => 0,
            'revoked' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        require_once database_path('migrations/2026_06_19_000001_add_grant_types_to_oauth_clients.php');
        (new (require database_path('migrations/2026_06_19_000001_add_grant_types_to_oauth_clients.php')))->up();

        $client = PassportClient::where('name', 'Regression Client')->first();

        $this->assertNotNull($client);
        $this->assertTrue($client->hasGrantType('client_credentials'));
        $this->assertFalse($client->hasGrantType('password'));
        $this->assertFalse($client->hasGrantType('authorization_code'));
    }
}
