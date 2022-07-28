<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToEntriesHistoryTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('entries_history', function(Blueprint $table)
		{
			$table->foreign('project_id', 'fk_entries_history_project_id')->references('id')->on('projects')->onUpdate('RESTRICT')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('entries_history', function(Blueprint $table)
		{
			$table->dropForeign('fk_entries_history_project_id');
		});
	}

}
