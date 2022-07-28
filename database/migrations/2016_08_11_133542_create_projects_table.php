<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateProjectsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('projects', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('name', 50);
			$table->string('slug', 50)->unique('projects_ref_UNIQUE');
			$table->string('ref', 100);
			$table->text('description');
			$table->text('small_description');
			$table->string('logo_url');
			$table->enum('access', array('public','private'))->default('public');
			$table->enum('visibility', array('listed','hidden'))->default('listed');
			$table->string('category', 100)->default('general');
			$table->integer('created_by')->index('fk_projects_user_id');
			$table->timestamp('created_at')->useCurrent();
			$table->timestamp('updated_at')->useCurrent();
			$table->enum('status', array('active','trashed','locked'))->default('active');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('projects');
	}

}
