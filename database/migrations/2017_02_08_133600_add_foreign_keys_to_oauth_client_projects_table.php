<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToOauthClientProjectsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('oauth_client_projects', function(Blueprint $table)
		{
			$table->foreign('project_id', 'fk_oauth_client_projects_project_id')->references('id')->on('projects')->onUpdate('NO ACTION')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('oauth_client_projects', function(Blueprint $table)
		{
			$table->dropForeign('fk_oauth_client_projects_project_id');
		});
	}

}
