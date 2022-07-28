<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddViewerRole extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE project_roles CHANGE COLUMN role role ENUM('creator', 'manager', 'curator', 'collector', 'viewer') NOT NULL DEFAULT 'collector'");

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE project_roles CHANGE COLUMN role role ENUM('creator', 'manager', 'curator', 'collector') NOT NULL DEFAULT 'collector'");
    }
}
