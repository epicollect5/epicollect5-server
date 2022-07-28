<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToProjectRolesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('project_roles', function(Blueprint $table)
		{
			$table->foreign('project_id', 'fk_project_roles_project_id')->references('id')->on('projects')->onUpdate('NO ACTION')->onDelete('CASCADE');
			$table->foreign('user_id', 'fk_project_roles_user_id')->references('id')->on('users')->onUpdate('NO ACTION')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('project_roles', function(Blueprint $table)
		{
			$table->dropForeign('fk_project_roles_project_id');
			$table->dropForeign('fk_project_roles_user_id');
		});
	}

}
