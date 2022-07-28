<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateOauthClientProjectsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('oauth_client_projects', function(Blueprint $table)
		{
			$table->integer('id', true);
            $table->integer('project_id')->index('fk_oauth_client_projects_project_id');
            $table->integer('client_id');
			$table->timestamp('created_at')->useCurrent();
			$table->timestamp('updated_at')->useCurrent();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('oauth_client_projects');
	}

}
