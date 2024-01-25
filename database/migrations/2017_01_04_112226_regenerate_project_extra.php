<?php

use ec5\DTO\ProjectStatsDTO;
use ec5\Http\Validation\Project\RuleForm as FormValidator;
use ec5\Http\Validation\Project\RuleInput as InputValidator;
use ec5\Http\Validation\Project\RuleProjectDefinition as ProjectDefinitionValidator;
use ec5\Http\Validation\Project\RuleProjectExtraDetails as ProjectExtraDetailsValidator;
use ec5\Models\Projects\Project;
use ec5\Models\Projects\ProjectDefinition;
use ec5\Models\Projects\ProjectExtra;
use ec5\Models\Projects\ProjectMapping;
use Illuminate\Database\Migrations\Migration;
use Symfony\Component\Console\Output\ConsoleOutput;

class RegenerateProjectExtra extends Migration
{

    /**
     * Run the migrations.
     *
     */
    public function up()
    {

        DB::beginTransaction();
        $output = new ConsoleOutput();

        $projects = DB::table(config('epicollect.tables.projects'))
            ->leftJoin(config('epicollect.tables.project_structures'), 'projects.id', '=', 'project_structures.project_id')
            ->select('projects.*', 'project_structures.project_definition')->get();

        foreach ($projects as $project) {

            // Need to instantiate models like this because we can't use the service container for dependency injection in a migration...
            $projectModel = new Project(new ProjectDefinition, new ProjectExtra, new ProjectMapping, new ProjectStatsDTO);
            $projectDefinitionValidator = new ProjectDefinitionValidator(new ProjectExtraDetailsValidator, new FormValidator, new InputValidator, new ProjectExtra, new ProjectDefinition);

            // Initialise with the project and the project_definition
            $projectModel->init($project);

            // Validate and generate Project Extra from Project Definition
            $projectDefinitionValidator->validate($projectModel);

            // Check for any errors so far
            if ($projectDefinitionValidator->hasErrors()) {
                $output->writeln('<info>' . 'Project ' . $project->id . ' failed.' . PHP_EOL . '</info>');
                continue;
            }

            $output->writeln('<info>' . 'Updating project extra for project ' . $project->id . PHP_EOL . '</info>');
            DB::table(config('epicollect.tables.project_structures'))->where('project_id', $project->id)->update(
                [
                    'project_extra' => $projectModel->getProjectExtra()->getJsonData()
                ]
            );
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
        //
    }
}
