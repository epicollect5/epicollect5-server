<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToBranchEntriesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('branch_entries', function(Blueprint $table)
		{
			$table->foreign('owner_entry_id', 'fk_branch_entries_entries_id')->references('id')->on('entries')->onUpdate('RESTRICT')->onDelete('CASCADE');
			$table->foreign('project_id', 'fk_branch_entries_project_id')->references('id')->on('projects')->onUpdate('RESTRICT')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('branch_entries', function(Blueprint $table)
		{
			$table->dropForeign('fk_branch_entries_entries_id');
			$table->dropForeign('fk_branch_entries_project_id');
		});
	}

}
