<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToBranchEntriesArchiveTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('branch_entries_archive', function(Blueprint $table)
		{
			$table->foreign('project_id', 'fk_branch_entries_archive_project_id')->references('id')->on('projects')->onUpdate('RESTRICT')->onDelete('CASCADE');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('branch_entries_archive', function(Blueprint $table)
		{
			$table->dropForeign('fk_branch_entries_archive_project_id');
		});
	}

}
