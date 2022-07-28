<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateEntriesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('entries', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('project_id')->index('fk_entries_project_id');
			$table->string('uuid')->unique('uuid');
			$table->string('parent_uuid', 200);
			$table->string('form_ref', 200)->default('');
			$table->string('parent_form_ref', 200)->default('');
			$table->integer('user_id')->nullable();
			$table->string('platform')->default('');
			$table->string('device_id')->default('');
			$table->timestamp('created_at')->useCurrent();
			$table->timestamp('uploaded_at')->useCurrent();
			$table->longText('title');
			$table->json('json_entry_data')->nullable();
			$table->json('json_geo_data')->nullable();
			$table->integer('child_counts')->default(0);
			$table->json('branch_counts')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('entries');
	}

}
