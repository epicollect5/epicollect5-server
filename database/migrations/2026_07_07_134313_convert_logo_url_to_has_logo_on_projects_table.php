<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('projects', 'logo_url') && !Schema::hasColumn('projects', 'has_logo')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->boolean('has_logo')->default(true)->after('small_description');
            });

            DB::table('projects')
                ->where(function ($q) {
                    $q->whereNull('logo_url')->orWhere('logo_url', '');
                })
                ->update(['has_logo' => false]);

            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('logo_url');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('projects', 'logo_url') && Schema::hasColumn('projects', 'has_logo')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('logo_url')->after('small_description')->nullable();
            });
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('has_logo');
            });
        }
    }
};
