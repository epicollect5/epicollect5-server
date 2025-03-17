<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(config('epicollect.tables.entries'), function (Blueprint $table) {
            $table->index(['project_id', 'form_ref', 'id'], 'idx_entries_project_form_ref_id');
        });
        Schema::table(config('epicollect.tables.branch_entries'), function (Blueprint $table) {
            $table->index(['project_id', 'form_ref', 'id'], 'idx_branch_entries_project_form_ref_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entries_and_branch_entries_tables', function (Blueprint $table) {
            $table->dropIndex('idx_entries_project_form_ref_id');
        });
        Schema::table(config('epicollect.tables.branch_entries'), function (Blueprint $table) {
            $table->dropIndex('idx_branch_entries_project_form_ref_id');
        });
    }
};
