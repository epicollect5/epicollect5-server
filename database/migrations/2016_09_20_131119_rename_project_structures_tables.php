<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameProjectStructuresTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE `project_structures` CHANGE `json_structure` `project_definition` JSON');
        DB::statement('ALTER TABLE `project_structures` CHANGE `json_structure_extra` `project_extra` JSON');
        DB::statement('ALTER TABLE `project_structures` CHANGE `json_forms_extra` `project_forms_extra` JSON');
        DB::statement('ALTER TABLE `project_structures` CHANGE `json_mapping_custom` `project_mapping` JSON');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('ALTER TABLE `project_structures` CHANGE `project_definition` `json_structure` JSON');
        DB::statement('ALTER TABLE `project_structures` CHANGE `project_extra` `json_structure_extra` JSON');
        DB::statement('ALTER TABLE `project_structures` CHANGE `project_forms_extra` `json_forms_extra` JSON');
        DB::statement('ALTER TABLE `project_structures` CHANGE `project_mapping` `json_mapping_custom` JSON');
    }
}
