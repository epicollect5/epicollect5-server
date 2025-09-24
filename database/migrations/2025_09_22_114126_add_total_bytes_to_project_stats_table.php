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
        // ── Drop the entire storage_stats table if it exists ───────────────────
        if (Schema::hasTable('storage_stats')) {
            Schema::drop('storage_stats');
        }



        Schema::table('project_stats', function (Blueprint $table) {

            // Drop `total_users` if it exists
            if (Schema::hasColumn('project_stats', 'total_users')) {
                $table->dropColumn('total_users');
            }

            $table->unsignedBigInteger('total_bytes')->default(0)->after('total_entries');
            $table->unsignedBigInteger('total_files')->default(0)->after('total_bytes');
            $table->unsignedBigInteger('photo_files')->default(0)->after('total_bytes');
            $table->unsignedBigInteger('photo_bytes')->default(0)->after('photo_files');
            $table->unsignedBigInteger('audio_files')->default(0)->after('photo_bytes');
            $table->unsignedBigInteger('audio_bytes')->default(0)->after('audio_files');
            $table->unsignedBigInteger('video_bytes')->default(0)->after('audio_bytes');
            $table->unsignedBigInteger('video_files')->default(0)->after('total_bytes');

            $table->timestamp('total_bytes_updated_at')->nullable()->after('total_bytes');
            // Add composite index for efficient project + storage queries
            $table->index(['project_id', 'total_bytes'], 'idx_project_stats_project_total_bytes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_stats', function (Blueprint $table) {
            // Drop `total_bytes` if it exists
            if (Schema::hasColumn('project_stats', 'total_bytes')) {
                $table->dropColumn('total_bytes');
            }
            if (Schema::hasColumn('project_stats', 'total_files')) {
                $table->dropColumn('total_files');
            }

            // Drop `total_bytes_updated_at` if it exists
            if (Schema::hasColumn('project_stats', 'total_bytes_updated_at')) {
                $table->dropColumn('total_bytes_updated_at');
            }
            if (Schema::hasColumn('project_stats', 'photo_bytes')) {
                $table->dropColumn('photo_bytes');
            }
            if (Schema::hasColumn('project_stats', 'audio_bytes')) {
                $table->dropColumn('audio_bytes');
            }
            if (Schema::hasColumn('project_stats', 'video_bytes')) {
                $table->dropColumn('video_bytes');
            }
            if (Schema::hasColumn('project_stats', 'video_files')) {
                $table->dropColumn('video_files');
            }
            if (Schema::hasColumn('project_stats', 'audio_files')) {
                $table->dropColumn('audio_files');
            }
            if (Schema::hasColumn('project_stats', 'photo_files')) {
                $table->dropColumn('photo_files');
            }
            if (Schema::hasIndex('project_stats', 'idx_project_stats_project_total_bytes')) {
                $table->dropIndex('idx_project_stats_project_total_bytes');
            }
        });
    }
};
