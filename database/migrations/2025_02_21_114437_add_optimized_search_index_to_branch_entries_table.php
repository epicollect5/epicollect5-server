<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Apply the migration by adding an optimized search index to the branch_entries table.
     *
     * This migration creates an index named "branch_entries_optimized_search" on the 
     * project_id, form_ref, and owner_input_ref columns to enhance query performance.
     */
    public function up(): void
    {
        Schema::table('branch_entries', function (Blueprint $table) {
            $table->index(['project_id', 'form_ref', 'owner_input_ref'], 'branch_entries_optimized_search');
        });
    }

    /**
     * Reverses the migration by removing the optimized search index from the branch_entries table.
     *
     * This method drops the 'branch_entries_optimized_search' index, effectively undoing the changes
     * applied by the migration's up() method.
     */
    public function down(): void
    {
        Schema::table('branch_entries', function (Blueprint $table) {
            $table->dropIndex('branch_entries_optimized_search');
        });
    }
};
