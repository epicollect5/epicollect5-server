<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Rename the column for environments that already applied the original
        // migration (which used the misleading name "client_secret_plain").
        // Fresh deployments get the new name directly from the original migration.
        if (
            Schema::hasColumn('oauth_client_projects', 'client_secret_plain')
            && !Schema::hasColumn('oauth_client_projects', 'client_secret_recoverable')
        ) {
            Schema::table('oauth_client_projects', function (Blueprint $table) {
                $table->renameColumn('client_secret_plain', 'client_secret_recoverable');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn('oauth_client_projects', 'client_secret_recoverable')
            && !Schema::hasColumn('oauth_client_projects', 'client_secret_plain')
        ) {
            Schema::table('oauth_client_projects', function (Blueprint $table) {
                $table->renameColumn('client_secret_recoverable', 'client_secret_plain');
            });
        }
    }
};
