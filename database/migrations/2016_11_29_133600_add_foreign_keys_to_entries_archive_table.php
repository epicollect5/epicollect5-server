<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToEntriesArchiveTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('entries_archive', function(Blueprint $table)
		{
			$table->foreign('project_id', 'fk_entries_archive_project_id')->references('id')->on('projects')->onUpdate('RESTRICT')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('entries_archive', function(Blueprint $table)
		{
			$table->dropForeign('fk_entries_archive_project_id');
		});
	}

}
