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
        $tableName = config('epicollect.tables.branch_entries_json');
        $branchEntriesTable = config('epicollect.tables.branch_entries');
        // Create the table with Schema Builder
        Schema::create($tableName, function (Blueprint $table) use ($branchEntriesTable) {
            $table->integer('entry_id')->primary();
            $table->integer('project_id')->nullable(false);
            $table->json('entry_data')->nullable(false);
            $table->json('geo_json_data')->nullable();
            $table->foreign('entry_id')->references('id')->on($branchEntriesTable)->onDelete('cascade');
        });

        // Alter table to set compression and key block size
        DB::statement("ALTER TABLE $tableName ROW_FORMAT=COMPRESSED, KEY_BLOCK_SIZE=4");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('epicollect.tables.branch_entries_json');
        Schema::dropIfExists($tableName);
    }
};
