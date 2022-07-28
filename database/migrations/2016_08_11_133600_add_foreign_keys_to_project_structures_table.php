<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToProjectStructuresTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('project_structures', function(Blueprint $table)
		{
			$table->foreign('project_id', 'fk_project_structures_project_id')->references('id')->on('projects')->onUpdate('NO ACTION')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('project_structures', function(Blueprint $table)
		{
			$table->dropForeign('fk_project_structures_project_id');
		});
	}

}
