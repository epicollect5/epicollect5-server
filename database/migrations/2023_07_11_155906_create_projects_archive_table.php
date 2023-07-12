<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProjectsArchiveTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('projects_archive', function (Blueprint $table) {
            $table->integer('id', false);
            $table->string('name', 50);
            $table->string('slug', 50)->unique('projects_ref_UNIQUE');
            $table->string('ref', 100);
            $table->text('description');
            $table->text('small_description');
            $table->string('logo_url');
            $table->enum('access', array('public', 'private'))->default('public');
            $table->enum('visibility', array('listed', 'hidden'))->default('listed');
            $table->string('category', 100)->default('general');
            $table->integer('created_by');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->enum('status', array('active', 'trashed', 'locked'))->default('trashed');
            $table->enum('can_bulk_upload', array('nobody', 'members', 'everybody'))
                ->default('nobody');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('projects_archive');
    }
}
