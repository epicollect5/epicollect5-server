<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStorageStatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('storage_stats', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('project_id')->unique();
            $table->string('project_ref', 100)->unique();
            $table->string('project_name', 50);
            $table->integer('files')->default(0);
            $table->integer('entries')->default(0);
            $table->timestamp('last_entry_uploaded')->nullable();
            $table->integer('branches')->default(0);
            $table->timestamp('last_branch_uploaded')->nullable();
            $table->bigInteger('audio_bytes')->default(0);
            $table->bigInteger('photo_bytes')->default(0);
            $table->bigInteger('video_bytes')->default(0);
            $table->bigInteger('overall_bytes')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('storage_stats');
    }
}
