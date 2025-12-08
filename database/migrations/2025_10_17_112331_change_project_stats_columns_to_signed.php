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
            // Change media byte counters to signed BIGINT
            $table->bigInteger('photo_bytes')->default(0)->change();
            $table->bigInteger('audio_bytes')->default(0)->change();
            $table->bigInteger('video_bytes')->default(0)->change();

            // Change media file counters to signed BIGINT
            $table->bigInteger('photo_files')->default(0)->change();
            $table->bigInteger('audio_files')->default(0)->change();
            $table->bigInteger('video_files')->default(0)->change();

            // Also make totals consistent
            $table->bigInteger('total_bytes')->default(0)->change();
            $table->bigInteger('total_files')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_stats', function (Blueprint $table) {
            // Roll back to UNSIGNED BIGINT (original type)
            $table->unsignedBigInteger('photo_bytes')->default(0)->change();
            $table->unsignedBigInteger('audio_bytes')->default(0)->change();
            $table->unsignedBigInteger('video_bytes')->default(0)->change();

            $table->unsignedBigInteger('photo_files')->default(0)->change();
            $table->unsignedBigInteger('audio_files')->default(0)->change();
            $table->unsignedBigInteger('video_files')->default(0)->change();

            $table->unsignedBigInteger('total_bytes')->default(0)->change();
            $table->unsignedBigInteger('total_files')->default(0)->change();
        });
    }
};
