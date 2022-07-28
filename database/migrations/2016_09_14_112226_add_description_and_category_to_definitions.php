<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDescriptionAndCategoryToDefinitions extends Migration
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
        // Update description
        DB::statement('UPDATE project_structures set project_structures.json_structure = JSON_SET(project_structures.json_structure, "$.project.description", (SELECT projects.description FROM projects WHERE projects.id=project_structures.project_id));');
        // Update category
        DB::statement('UPDATE project_structures set project_structures.json_structure = JSON_SET(project_structures.json_structure, "$.project.category", (SELECT projects.category FROM projects WHERE projects.id=project_structures.project_id));');

        // Project Extra
        // Update description
        DB::statement('UPDATE project_structures set project_structures.json_structure_extra = JSON_SET(project_structures.json_structure_extra, "$.project.details.description", (SELECT projects.description FROM projects WHERE projects.id=project_structures.project_id));');
        // Update category
        DB::statement('UPDATE project_structures set project_structures.json_structure_extra = JSON_SET(project_structures.json_structure_extra, "$.project.details.category", (SELECT projects.category FROM projects WHERE projects.id=project_structures.project_id));');

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
        // Update description
        DB::statement('UPDATE project_structures SET project_structures.json_structure = (SELECT JSON_REMOVE(project_structures.json_structure, "$.project.description"));');
        // Update category
        DB::statement('UPDATE project_structures SET project_structures.json_structure = (SELECT JSON_REMOVE(project_structures.json_structure, "$.project.category"));');

        // Project Extra
        // Update description
        DB::statement('UPDATE project_structures SET project_structures.json_structure_extra = (SELECT JSON_REMOVE(project_structures.json_structure_extra, "$.project.details.description"));');
        // Update category
        DB::statement('UPDATE project_structures SET project_structures.json_structure_extra = (SELECT JSON_REMOVE(project_structures.json_structure_extra, "$.project.details.category"));');

        DB::commit();
    }
}
