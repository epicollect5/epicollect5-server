<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Passport 13's Bridge\ClientRepository::fromClientModel
        // (vendor/laravel/passport/src/Bridge/ClientRepository.php:55-65)
        // reads $model->grant_types and $model->redirect_uris unconditionally.
        // On the pre-13 oauth_clients schema (database/migrations/2016_06_01_000004)
        // these columns do not exist, so existing clients authenticate and then
        // fail at /oauth/token with "unsupported_grant_type" because
        // Client::hasGrantType() returns false on null.
        // See AGENTS.md "## Laravel Upgrades" point 6 for the case study.
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->text('grant_types')->nullable()->after('password_client');
            $table->text('redirect_uris')->nullable()->after('redirect');
        });

        // Backfill from old schema. This app's only client-creation path is
        // ProjectAppsController::store -> ClientRepository::createClientCredentialsGrantClient
        // (vendor/laravel/passport/src/ClientRepository.php:141-144), so every
        // existing row in oauth_clients is by-intent a 'client_credentials'
        // client. The old boolean fallback in ClientRepository::create()
        // (lines 107-112) wrote password_client=0, personal_access_client=0
        // for a client_credentials client, so the booleans are not a reliable
        // signal — the grant type must be derived from the call site.
        DB::table('oauth_clients')->update([
            'grant_types' => json_encode(['client_credentials']),
            'redirect_uris' => json_encode([]),
        ]);

        // Lock it down — prevents future null values reaching hasGrantType()
        // (vendor/laravel/passport/src/Client.php:204-207) and silently breaking
        // OAuth for any newly-inserted client. Without this, a developer who
        // adds a new client-creation path that forgets grant_types produces a
        // row that authenticates with 200 OK on a token request but throws
        // "unsupported_grant_type" — the exact bug this migration fixes.
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->text('grant_types')->nullable(false)->change();
            $table->text('redirect_uris')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn(['grant_types', 'redirect_uris']);
        });
    }
};
