<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropUniqueSlugIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //drop unique slug constraint on projects table
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_ref_UNIQUE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //restore unique constraints
        Schema::table('projects', function ($table) {
            $table->unique('slug', 'projects_ref_UNIQUE');
        });
    }
}
