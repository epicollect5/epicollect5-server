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
        Schema::table('project_stats', function (Blueprint $table) {
            $table->index('total_entries', 'idx_project_stats_total_entries');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_stats', function (Blueprint $table) {
            $table->dropIndex('idx_project_stats_total_entries');
        });
    }
};
