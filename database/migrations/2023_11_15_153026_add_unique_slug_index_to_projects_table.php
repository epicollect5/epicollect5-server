<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUniqueSlugIndexToProjectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //restore unique constraints
        Schema::table('projects', function ($table) {
            $table->unique('slug', 'slug_UNIQUE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //drop unique slug constraint on projects table
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('slug_UNIQUE');
        });
    }
}
