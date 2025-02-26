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
        Schema::table('branch_entries', function (Blueprint $table) {
            $table->index(['project_id', 'form_ref', 'owner_input_ref'], 'branch_entries_optimized_search');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branch_entries', function (Blueprint $table) {
            $table->dropIndex('branch_entries_optimized_search');
        });
    }
};
