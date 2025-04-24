<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if column exists before trying to drop it
        if (Schema::hasColumn('entries', 'entry_data_compressed')) {
            Schema::table('entries', function (Blueprint $table) {
                $table->dropColumn('entry_data_compressed');
            });
        }

        if (Schema::hasColumn('entries', 'geo_json_data_compressed')) {
            Schema::table('entries', function (Blueprint $table) {
                $table->dropColumn('geo_json_data_compressed');
            });
        }

        Schema::dropIfExists('entries_json');


        // Create a new table for storing compressed JSON data
        Schema::create('entries_json', function (Blueprint $table) {
            $table->integer('entry_id')->primary();
            $table->longText('entry_data_compressed')->charset('binary')->nullable(); // LONGBLOB
            $table->longText('geo_json_data_compressed')->charset('binary')->nullable(); // LONGBLOB

            // Add foreign key constraint
            $table->foreign('entry_id')
                ->references('id')
                ->on('entries')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });

        // Copy and compress data in batches
        $total = DB::table('entries')->count();
        $batchSize = 5000;

        echo "Starting to process  entries";

        // Process directly with raw SQL in batches for better performance
        DB::statement("
            INSERT INTO entries_json (entry_id, entry_data_compressed, geo_json_data_compressed)
            SELECT 
                id, 
                COMPRESS(entry_data), 
                CASE WHEN geo_json_data IS NOT NULL THEN COMPRESS(geo_json_data) ELSE NULL END
            FROM entries
            WHERE entry_data IS NOT NULL
        ");

        echo "Peak memoery usage: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entries_json');
    }
};
