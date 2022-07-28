<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToProjectsFeaturedTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('projects_featured', function(Blueprint $table)
		{
			$table->foreign('project_id', 'fk_projects_featured_project_id')->references('id')->on('projects')->onUpdate('NO ACTION')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('projects_featured', function(Blueprint $table)
		{
			$table->dropForeign('fk_projects_featured_project_id');
		});
	}

}
