<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameEntriesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE `entries` CHANGE `json_entry_data` `entry_data` JSON');
        DB::statement('ALTER TABLE `entries` CHANGE `json_geo_data` `geo_json_data` JSON');

        DB::statement('ALTER TABLE `entries_history` CHANGE `json_entry_data` `entry_data` JSON');
        DB::statement('ALTER TABLE `entries_history` CHANGE `json_geo_data` `geo_json_data` JSON');

        DB::statement('ALTER TABLE `branch_entries` CHANGE `json_entry_data` `entry_data` JSON');
        DB::statement('ALTER TABLE `branch_entries` CHANGE `json_geo_data` `geo_json_data` JSON');

        DB::statement('ALTER TABLE `branch_entries_history` CHANGE `json_entry_data` `entry_data` JSON');
        DB::statement('ALTER TABLE `branch_entries_history` CHANGE `json_geo_data` `geo_json_data` JSON');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('ALTER TABLE `entries` CHANGE `entry_data` `json_entry_data` JSON');
        DB::statement('ALTER TABLE `entries` CHANGE `geo_json_data` `json_geo_data` JSON');

        DB::statement('ALTER TABLE `entries_history` CHANGE `entry_data` `json_entry_data` JSON');
        DB::statement('ALTER TABLE `entries_history` CHANGE `geo_json_data` `json_geo_data` JSON');

        DB::statement('ALTER TABLE `branch_entries` CHANGE `entry_data` `json_entry_data` JSON');
        DB::statement('ALTER TABLE `branch_entries` CHANGE `geo_json_data` `json_geo_data` JSON');

        DB::statement('ALTER TABLE `branch_entries_history` CHANGE `entry_data` `json_entry_data` JSON');
        DB::statement('ALTER TABLE `branch_entries_history` CHANGE `geo_json_data` `json_geo_data` JSON');
    }
}
