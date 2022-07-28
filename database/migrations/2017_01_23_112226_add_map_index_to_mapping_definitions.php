<?php

use Illuminate\Database\Migrations\Migration;
use Symfony\Component\Console\Output\ConsoleOutput;

class AddMapIndexToMappingDefinitions extends Migration
{
    /**
     * Run the migrations.
     * Remove device_id and platform properties from the json entry_data
     *
     * @return void
     */
    public function up()
    {
        DB::beginTransaction();
        $output = new ConsoleOutput();

        $projectMappings = DB::table('project_structures')->select('project_mapping', 'id')->get();
        foreach ($projectMappings as $projectMapping) {

            $mappings = json_decode($projectMapping->project_mapping, true);
            $newMapping = [];

            // Check we have an array
            if (is_array($mappings)) {
                foreach ($mappings as $mapIndex => $mapping) {
                    // Add the map_index property
                    $mapping['map_index'] = $mapIndex;
                    $newMapping[$mapIndex] = $mapping;
                    $output->writeln("<info>" . " project_structures: adding map_index in mapping for id - " . $projectMapping->id . PHP_EOL . "</info>");

                }

                DB::table('project_structures')->where('id', $projectMapping->id)->update(['project_mapping' => json_encode($newMapping)]);

            }
        }

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
        $output = new ConsoleOutput();

        $projectMappings = DB::table('project_structures')->select('project_mapping', 'id')->get();
        foreach ($projectMappings as $projectMapping) {

            $mappings = json_decode($projectMapping->project_mapping, true);
            $newMapping = [];

            // Check we have an array
            if (is_array($mappings)) {
                foreach ($mappings as $mapIndex => $mapping) {
                    // Add the map_index property
                    unset($mapping['map_index']);
                    $newMapping[$mapIndex] = $mapping;
                    $output->writeln("<info>" . " project_structures: removing map_index in mapping for id - " . $projectMapping->id . PHP_EOL . "</info>");

                }

                DB::table('project_structures')->where('id', $projectMapping->id)->update(['project_mapping' => json_encode($newMapping)]);

            }
        }

        DB::commit();

    }
}
