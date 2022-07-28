<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCompositeIndexToBranchEntriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('branch_entries', function(Blueprint $table)
        {
            //remember to use underscore for index name as '-' would not be valid!
            $table->index(['project_id', 'owner_input_ref', 'created_at'], 'branch_entries_search');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('branch_entries', function (Blueprint $table) {
            $table->dropIndex('branch_entries_search'); // Drops index
        });
    }
}
