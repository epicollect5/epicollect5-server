<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_client_projects', function (Blueprint $table) {
            $table->string('client_secret_plain')->nullable()->after('client_id');
        });

        // Copy existing plain-text secrets from oauth_clients to oauth_client_projects
        // This must run BEFORE passport:hash, which will permanently hash the secrets
        DB::table('oauth_client_projects')
            ->join('oauth_clients', 'oauth_client_projects.client_id', '=', 'oauth_clients.id')
            ->update([
                'oauth_client_projects.client_secret_plain' => DB::raw('oauth_clients.secret')
            ]);
    }

    public function down(): void
    {
        Schema::table('oauth_client_projects', function (Blueprint $table) {
            $table->dropColumn('client_secret_plain');
        });
    }
};
