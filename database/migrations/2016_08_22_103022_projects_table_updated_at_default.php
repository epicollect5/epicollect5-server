<?php

use Illuminate\Database\Migrations\Migration;

class ProjectsTableUpdatedAtDefault extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Because the projects table contains 'enum' columns, we cannot update using Laravel
        DB::statement('ALTER TABLE `projects` CHANGE `updated_at` `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Because the projects table contains 'enum' columns, we cannot update using Laravel
        DB::statement('ALTER TABLE `projects` CHANGE `updated_at` `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }
}
