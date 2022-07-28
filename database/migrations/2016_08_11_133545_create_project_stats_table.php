<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateProjectStatsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('project_stats', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('project_id')->index('fk_project_stats_project_id');
			$table->integer('total_entries');
			$table->integer('total_users');
			$table->json('form_counts')->nullable();
			$table->json('branch_counts')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('project_stats');
	}

}
