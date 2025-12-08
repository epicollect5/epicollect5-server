<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBranchEntriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('branch_entries', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('project_id')->index('fk_branch_entries_project_id');
            $table->string('uuid')->unique('uuid');
            $table->integer('owner_entry_id')->index('fk_branch_entries_entries_id');
            $table->string('owner_uuid', 200)->default('');
            $table->string('owner_input_ref', 200);
            $table->string('form_ref', 200)->default('');
            $table->integer('user_id')->nullable();
            $table->string('platform')->default('');
            $table->string('device_id')->default('');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->longText('title');
            $table->json('json_entry_data')->nullable();
            $table->json('json_geo_data')->nullable();
        });
        DB::statement('ALTER TABLE branch_entries ROW_FORMAT=COMPRESSED, KEY_BLOCK_SIZE=4');
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('branch_entries');
    }

}
