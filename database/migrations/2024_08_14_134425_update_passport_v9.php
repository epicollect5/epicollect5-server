<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdatePassportV9 extends Migration
{
    /**
     * Run the migrations.
     *
     */
    public function up(): void
    {
        // Check if the 'provider' column already exists in the 'oauth_clients' table
        if (!Schema::hasColumn('oauth_clients', 'provider')) {
            Schema::table('oauth_clients', function (Blueprint $table) {
                $table->string('provider')->after('secret')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        // Check if the 'provider' column exists before trying to drop it
        if (Schema::hasColumn('oauth_clients', 'provider')) {
            Schema::table('oauth_clients', function (Blueprint $table) {
                $table->dropColumn('provider');
            });
        }
    }
}
