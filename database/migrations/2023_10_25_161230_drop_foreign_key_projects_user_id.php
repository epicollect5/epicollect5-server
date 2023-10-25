<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropForeignKeyProjectsUserId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //drop foreign key constraint on entries table
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign('fk_projects_user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //restore foreign keys constraints
        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('created_by', 'fk_projects_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }
}
