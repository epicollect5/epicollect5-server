<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateProjectStructuresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('project_structures', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('project_id')->index('fk_project_structures_project_id');
            $table->json('json_structure')->nullable();
            $table->json('json_structure_extra')->nullable();
            $table->json('json_forms_extra')->nullable();
            $table->json('json_mapping_custom')->nullable();
            $table->timestamp('updated_at')->useCurrent();
        });
        DB::statement('ALTER TABLE project_structures ROW_FORMAT=COMPRESSED, KEY_BLOCK_SIZE=4');
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('project_structures');
    }

}
