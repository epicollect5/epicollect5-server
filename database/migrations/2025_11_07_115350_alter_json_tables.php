<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $entriesJsonTableName = config('epicollect.tables.entries_json');
        $branchEntriesJsonTableName = config('epicollect.tables.branch_entries_json');
        Schema::table($entriesJsonTableName, function (Blueprint $table) {
            // Add project_id column after entry_id
            $table->integer('project_id')->after('entry_id')->nullable(false);

            // Modify entry_data to be not nullable
            $table->json('entry_data')->nullable(false)->change();
        });
        Schema::table($branchEntriesJsonTableName, function (Blueprint $table) {
            // Add project_id column after entry_id
            $table->integer('project_id')->after('entry_id')->nullable(false);

            // Modify entry_data to be not nullable
            $table->json('entry_data')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //Reverse the migration
        $entriesJsonTableName = config('epicollect.tables.entries_json');
        $branchEntriesJsonTableName = config('epicollect.tables.branch_entries_json');
        Schema::table($entriesJsonTableName, function (Blueprint $table) {
            // Drop project_id column
            $table->dropColumn('project_id');

            // Modify entry_data to be nullable
            $table->json('entry_data')->nullable()->change();
        });
        Schema::table($branchEntriesJsonTableName, function (Blueprint $table) {
            // Drop project_id column
            $table->dropColumn('project_id');

            // Modify entry_data to be nullable
            $table->json('entry_data')->nullable()->change();
        });
    }
};
