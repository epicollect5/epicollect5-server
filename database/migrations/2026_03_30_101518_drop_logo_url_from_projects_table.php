<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('projects', 'logo_url')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('logo_url');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('projects', 'logo_url')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('logo_url')->after('small_description')->nullable();
            });
        }
    }
};
