<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class DropFkEntriesProjectId extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->dropForeignKeyIfExists('entries', 'fk_entries_project_id');
        $this->dropForeignKeyIfExists('branch_entries', 'fk_branch_entries_project_id');
        if (Schema::hasTable('entries_archive')) {
            $this->dropForeignKeyIfExists('entries_archive', 'fk_entries_archive_project_id');
        }
        if (Schema::hasTable('branch_entries_archive')) {
            $this->dropForeignKeyIfExists('branch_entries_archive', 'fk_branch_entries_archive_project_id');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->foreign('project_id', 'fk_entries_project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('cascade');
        });

        Schema::table('branch_entries', function (Blueprint $table) {
            $table->foreign('project_id', 'fk_branch_entries_project_id')
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

    /**
     * Drop foreign key if it exists.
     */
    protected function foreignKeyExists(string $table, string $foreignKey): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $foreignKey)
            ->exists();
    }

    protected function dropForeignKeyIfExists(string $table, string $foreignKey): void
    {
        if ($this->foreignKeyExists($table, $foreignKey)) {
            Schema::table($table, function (Blueprint $table) use ($foreignKey) {
                $table->dropForeign($foreignKey);
            });
        }
    }

}
