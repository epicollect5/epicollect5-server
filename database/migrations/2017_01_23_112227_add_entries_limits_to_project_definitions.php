<?php

use Illuminate\Database\Migrations\Migration;
use Symfony\Component\Console\Output\ConsoleOutput;

class AddEntriesLimitsToProjectDefinitions extends Migration
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

        $projectStructures = DB::table('project_structures')->select(
            'project_definition',
            'project_extra',
            'id'
        )->get();

        foreach ($projectStructures as $projectStructure) {

            $definition = json_decode($projectStructure->project_definition, true);
            $extra = json_decode($projectStructure->project_extra, true);
            // Add empty entries_limits property
            $definition['project']['entries_limits'] = [];
            // Add empty entries_limits property
            $extra['project']['entries_limits'] = [];

            $output->writeln("<info>" . " project_structures: adding entries_limits for id - " . $projectStructure->id . PHP_EOL . "</info>");

            DB::table('project_structures')->where('id',
                $projectStructure->id)->update([
                'project_definition' => json_encode($definition),
                'project_extra' => json_encode($extra)
            ]);
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

        $projectStructures = DB::table('project_structures')->select(
            'project_definition',
            'project_extra',
            'id'
        )->get();
        foreach ($projectStructures as $projectStructure) {

            $definition = json_decode($projectStructure->project_definition, true);
            // Remove entries_limits property
            unset($definition['project']['entries_limits']);

            $extra = json_decode($projectStructure->project_extra, true);
            // Remove entries_limits property
            unset($extra['project']['entries_limits']);

            $output->writeln("<info>" . " project_structures: removing entries_limits for id - " . $projectStructure->id . PHP_EOL . "</info>");

            DB::table('project_structures')->where('id',
                $projectStructure->id)->update([
                'project_definition' => json_encode($definition),
                'project_extra' => json_encode($extra)
            ]);
        }

        DB::commit();

    }
}
