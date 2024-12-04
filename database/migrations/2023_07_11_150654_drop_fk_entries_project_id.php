<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

class DropFkEntriesProjectId extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop foreign key constraint on entries table
        Schema::table('entries', function (Blueprint $table) {
            try {
                $table->dropForeign('fk_entries_project_id');
            } catch (Throwable $e) {
                Log::error(__METHOD__ . ' failed on entries table.', ['exception' => $e->getMessage()]);
            }
        });

        // Drop foreign key constraint on branch_entries table
        Schema::table('branch_entries', function (Blueprint $table) {
            try {
                $table->dropForeign('fk_branch_entries_project_id');
            } catch (Throwable $e) {
                Log::error(__METHOD__ . ' failed on branch_entries table.', ['exception' => $e->getMessage()]);
            }
        });

        // Drop foreign key constraint on entries_archive table if it exists
        if (Schema::hasTable('entries_archive')) {
            Schema::table('entries_archive', function (Blueprint $table) {
                try {
                    $table->dropForeign('fk_entries_archive_project_id');
                } catch (Throwable $e) {
                    Log::error(__METHOD__ . ' failed on entries_archive table.', ['exception' => $e->getMessage()]);
                }
            });
        }

        // Drop foreign key constraint on branch_entries_archive table if it exists
        if (Schema::hasTable('branch_entries_archive')) {
            Schema::table('branch_entries_archive', function (Blueprint $table) {
                try {
                    $table->dropForeign('fk_branch_entries_archive_project_id');
                } catch (Throwable $e) {
                    Log::error(__METHOD__ . ' failed on branch_entries_archive table.', ['exception' => $e->getMessage()]);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore foreign key constraint on entries table
        Schema::table('entries', function (Blueprint $table) {
            try {
                $table->foreign('project_id', 'fk_entries_project_id')
                    ->references('id')
                    ->on('projects')
                    ->onDelete('cascade');
            } catch (Throwable $e) {
                Log::error(__METHOD__ . ' failed to restore on entries table.', ['exception' => $e->getMessage()]);
            }
        });

        // Restore foreign key constraint on branch_entries table
        Schema::table('branch_entries', function (Blueprint $table) {
            try {
                $table->foreign('project_id', 'fk_branch_entries_project_id')
                    ->references('id')
                    ->on('projects')
                    ->onDelete('cascade');
            } catch (Throwable $e) {
                Log::error(__METHOD__ . ' failed to restore on branch_entries table.', ['exception' => $e->getMessage()]);
            }
        });

        // Restore foreign key constraint on entries_archive table if it exists
        if (Schema::hasTable('entries_archive')) {
            Schema::table('entries_archive', function (Blueprint $table) {
                try {
                    $table->foreign('project_id', 'fk_entries_archive_project_id')
                        ->references('id')
                        ->on('projects')
                        ->onDelete('cascade');
                } catch (Throwable $e) {
                    Log::error(__METHOD__ . ' failed to restore on entries_archive table.', ['exception' => $e->getMessage()]);
                }
            });
        }

        // Restore foreign key constraint on branch_entries_archive table if it exists
        if (Schema::hasTable('branch_entries_archive')) {
            Schema::table('branch_entries_archive', function (Blueprint $table) {
                try {
                    $table->foreign('project_id', 'fk_branch_entries_archive_project_id')
                        ->references('id')
                        ->on('projects')
                        ->onDelete('cascade');
                } catch (Throwable $e) {
                    Log::error(__METHOD__ . ' failed to restore on branch_entries_archive table.', ['exception' => $e->getMessage()]);
                }
            });
        }
    }
}
