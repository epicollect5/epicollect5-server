<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Add covering indexes for child-traversal (parent_uuid) and uploaded_at queries on entries.
     */
    public function up(): void
    {
        Schema::table(config('epicollect.tables.entries'), function (Blueprint $table) {
            $table->index(['project_id', 'parent_uuid', 'created_at'], 'idx_entries_project_parent_uuid_created_at');
            $table->index(['project_id', 'form_ref', 'uploaded_at'], 'idx_entries_project_form_ref_uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::table(config('epicollect.tables.entries'), function (Blueprint $table) {
            $table->dropIndex('idx_entries_project_parent_uuid_created_at');
            $table->dropIndex('idx_entries_project_form_ref_uploaded_at');
        });
    }
};
