<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIdToDefinitions extends Migration
{
    /**
     * Run the migrations.
     * This is a migration to add the 'description' and 'category' keys and values
     * to the Project Definition and Project Extra JSON objects in the database
     *
     * @return void
     */
    public function up()
    {

        DB::beginTransaction();

        // Project Definition
        // Update ID
        DB::statement('UPDATE project_structures set project_structures.project_definition = JSON_SET(project_structures.project_definition, "$.id", (SELECT projects.ref FROM projects WHERE projects.id=project_structures.project_id));');

        DB::commit();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::beginTransaction();
        
        // Project Definition
        // Remove ID
        DB::statement('UPDATE project_structures SET project_structures.project_definition = (SELECT JSON_REMOVE(project_structures.project_definition, "$.id"));');

        DB::commit();
    }
}
