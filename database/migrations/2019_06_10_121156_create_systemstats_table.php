<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSystemstatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('system_stats', function (Blueprint $table) {
            $table->integer('id', true);
            $table->json('user_stats')->nullable();
            $table->json('project_stats')->nullable();
            $table->json('entries_stats')->nullable();
            $table->json('branch_entries_stats')->nullable();
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
        Schema::drop('system_stats');
    }
}
