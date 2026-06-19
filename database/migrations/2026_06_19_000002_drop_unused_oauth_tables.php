<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Tables backing OAuth grant types this app has never enabled.
        // Verified by grep across app/, config/, routes/, tests/: no
        // createAuthorizationCodeGrantClient / refresh-token-handling /
        // personalAccessClient / device_code call sites.
        //
        // oauth_personal_access_clients: Passport 13 UPGRADE.md
        // (vendor/laravel/passport/UPGRADE.md:116-124) explicitly states
        // "Passport no longer interacts with this table or its
        // corresponding model" and recommends dropping it. The table was
        // not empty on production: 1 row referencing oauth_clients.id=1
        // from 2017-06-21 (the original personal access client). The row
        // is intentionally dropped — Passport 13 ignores the table and
        // this app uses client_credentials exclusively.
        //
        // oauth_auth_codes: authorization_code grant — not enabled.
        // oauth_refresh_tokens: refresh_token grant — not enabled (the
        // app's OAuthController::issueToken only handles access tokens,
        // not refreshes).
        Schema::dropIfExists('oauth_personal_access_clients');
        Schema::dropIfExists('oauth_auth_codes');
        Schema::dropIfExists('oauth_refresh_tokens');
    }

    public function down(): void
    {
        // Recreate from the original 2016 migrations rather than
        // duplicating the schema, so the rollback path stays in sync if
        // Passport ever changes the table shape.
        if (!Schema::hasTable('oauth_personal_access_clients')) {
            (require database_path('migrations/2016_06_01_000005_create_oauth_personal_access_clients_table.php'))->up();
        }
        if (!Schema::hasTable('oauth_auth_codes')) {
            (require database_path('migrations/2016_06_01_000001_create_oauth_auth_codes_table.php'))->up();
        }
        if (!Schema::hasTable('oauth_refresh_tokens')) {
            (require database_path('migrations/2016_06_01_000003_create_oauth_refresh_tokens_table.php'))->up();
        }
    }
};
