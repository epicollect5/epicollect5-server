<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropFkEntriesProjectId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //drop foreign key constraint on entries table
        Schema::table('entries', function (Blueprint $table) {
            $table->dropForeign('fk_entries_project_id');
        });

        //drop foreign key constraint on branch_entries table
        Schema::table('branch_entries', function (Blueprint $table) {
            $table->dropForeign('fk_branch_entries_project_id');
        });

        // Drop foreign key constraint on entries_archive table if it exists
        if (Schema::hasTable('entries_archive')) {
            Schema::table('entries_archive', function (Blueprint $table) {
                $table->dropForeign('fk_entries_archive_project_id');
            });
        }

        // Drop foreign key constraint on branch_entries_archive table if it exists
        if (Schema::hasTable('branch_entries_archive')) {
            Schema::table('branch_entries_archive', function (Blueprint $table) {
                $table->dropForeign('fk_branch_entries_archive_project_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //restore foreign keys constraints
        Schema::table('entries', function (Blueprint $table) {
            $table->foreign('project_id', 'fk_entries_project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('cascade');
        });

        if (Schema::hasTable('entries_archive')) {
            Schema::table('entries_archive', function (Blueprint $table) {
                $table->foreign('project_id', 'fk_entries_archive_project_id')
                    ->references('id')
                    ->on('projects')
                    ->onDelete('cascade');
            });
        }

        if (Schema::hasTable('branch_entries_archive')) {
            Schema::table('branch_entries_archive', function (Blueprint $table) {
                $table->foreign('project_id', 'fk_branch_entries_archive_project_id')
                    ->references('id')
                    ->on('projects')
                    ->onDelete('cascade');
            });
        }
    }
}
