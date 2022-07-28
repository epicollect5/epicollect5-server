<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateProjectRolesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('project_roles', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('project_id')->index('fk_project_roles_project_id');
			$table->integer('user_id')->index('fk_project_roles_user_id');
			$table->enum('role', array('creator','manager','curator','collector'))->default('collector');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('project_roles');
	}

}
