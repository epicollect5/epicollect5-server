<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCompositeIndexToEntriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('entries', function(Blueprint $table)
        {
            //remember to use underscore for index name as '-' would not be valid!
            $table->index(['project_id', 'form_ref', 'created_at'], 'entries_search');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropIndex('entries_search'); // Drops index
        });
    }
}
