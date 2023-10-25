<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddArchivedStatusToProjects extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE projects MODIFY COLUMN status ENUM('active','trashed','locked', 'archived') DEFAULT 'active' NOT NULL;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE projects MODIFY COLUMN status ENUM('active','trashed','locked') DEFAULT 'active' NOT NULL;");
    }
}
