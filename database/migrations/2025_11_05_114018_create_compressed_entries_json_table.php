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
        $tableName = config('epicollect.tables.entries_json');

        // Create the table with Schema Builder
        Schema::create($tableName, function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('entry_id')->unique();
            $table->integer('project_id')->nullable(false);
            $table->json('entry_data')->nullable(false);
            $table->json('geo_json_data')->nullable();
            $table->foreign('entry_id')->references('id')->on('entries')->onDelete('cascade');
        });

        // Alter table to set compression and key block size
        DB::statement("ALTER TABLE $tableName ROW_FORMAT=COMPRESSED, KEY_BLOCK_SIZE=4");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('epicollect.tables.entries_json');
        Schema::dropIfExists($tableName);
    }
};
