<?php

namespace Database\Seeders;

use App;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Traits\Eloquent\Remover;
use Illuminate\Database\Seeder;
use Tests\Generators\EntryGenerator;

class EntriesSeeder extends Seeder
{
    use Remover;
    /**
     * Run the database seeds.
     *
     * imp: php artisan db:seed --class=EntriesSeeder
     * imp: php artisan seed:entries
     *
     * @return void
     * @noinspection DuplicatedCode
     */
    public function run(): void
    {
        if (App::environment('production')) {
            $this->command->info('Skipping seeder in production environment.');
            return;
        }

        $projectId = (int) $this->command->ask('Please enter the project ID', 7);
        $numOfEntries = (int) $this->command->ask('Please enter the number of entries', 5);

        $project = Project::find($projectId);

        if ($project === null) {
            $this->command->error('Project not found.');
            return;
        }

        // Confirm project name
        $projectName = $project->name;
        $proceed = strtolower($this->command->ask("The project name is '$projectName'. Proceed? (y/n)", 'n'));

        if ($proceed !== 'y') {
            $this->command->warn('Operation aborted.');
            return;
        }

        $entries = Entry::where('project_id', $project->id)->get();
        if (sizeof($entries) > 0) {
            $proceed = strtolower($this->command->ask("Delete existing entries? (y/n)", 'n'));
            if ($proceed === 'y') {
                //delete entries
                Entry::where('project_id', $project->id)->delete();
                //delete all media files
                $this->removeAllTheEntriesMediaFolders($project->ref);
            }
        }

        $projectStructure = ProjectStructure::where('project_id', $project->id)->first();
        $projectDefinition = ['data' => json_decode($projectStructure->project_definition, true)];
        $entryGenerator = new EntryGenerator($projectDefinition);

        $formRef = array_get($projectDefinition, 'data.project.forms.0.ref');
        //get any branch inputs
        $branches = $this->getFormBranches($projectDefinition, 0);

        // Get the console output instance
        $output = $this->command->getOutput();

        // Loop to insert the specified number of entries
        $entryPayloads = [];
        $branchEntryPayloads = [];
        for ($i = 0; $i < $numOfEntries; $i++) {
            $entryPayloads[$i] = $entryGenerator->createParentEntryPayload($formRef);
            $entryGenerator->createParentEntryRow(
                User::find($project->created_by),
                $project,
                config('epicollect.strings.project_roles.creator'),
                $projectDefinition,
                $entryPayloads[$i]
            );
            // Show progress
            $num = $i + 1;
            $output->write("\rInserted $num parent entries...    ");

            //if any branch, generate x branch entries
            $numOfBranchEntries = rand(2, 5);
            for ($j = 0; $j < $numOfBranchEntries; $j++) {
                foreach ($branches as $ownerInputRef => $branchInputs) {
                    $branchEntryPayloads[$j] = $entryGenerator->createBranchEntryPayload(
                        $formRef,
                        $branchInputs,
                        $entryPayloads[$i]['data']['id'],
                        $ownerInputRef
                    );
                    $entryGenerator->createBranchEntryRow(
                        User::find($project->created_by),
                        $project,
                        config('epicollect.strings.project_roles.creator'),
                        $projectDefinition,
                        $branchEntryPayloads[$j]
                    );
                    $output->writeln("\rInserted branch entries...    ");
                }
            }
        }

        //loop all the child forms, if any
        $forms = array_get($projectDefinition, 'data.project.forms');
        foreach ($forms as $formIndex => $form) {
            if($formIndex > 0) {

                $output->writeln("\rAdding child entries for form level $formIndex...    ");

                //for each parent entry, add some child entries
                $parentFormRef = array_get($forms, $formIndex - 1)['ref'];
                $childFormRef = $form['ref'];
                $parentEntries = Entry::where('project_id', $project->id)
                    ->where('form_ref', $parentFormRef)
                    ->get();

                //get any branch inputs
                $childBranches = $this->getFormBranches($projectDefinition, $formIndex);
                $numOfChildEntries = rand(1, 2);
                foreach ($parentEntries as $parentEntry) {
                    for ($x = 0; $x < $numOfChildEntries; $x++) {

                        $childEntryPayload = $entryGenerator->createChildEntryPayload($childFormRef, $parentFormRef, $parentEntry->uuid);
                        $entryGenerator->createChildEntryRow(
                            User::find($project->created_by),
                            $project,
                            config('epicollect.strings.project_roles.creator'),
                            $projectDefinition,
                            $childEntryPayload
                        );
                        // Show progress
                        $numChildren = $x + 1;
                        $output->write("\rInserted $numChildren child entries level $formIndex...    ");

                        //if any branch in the child form, generate x branch entries
                        $numOfChildBranchEntries = rand(1, 2);
                        for ($j = 0; $j < $numOfChildBranchEntries; $j++) {
                            foreach ($childBranches as $ownerInputRef => $childBranchInputs) {
                                $branchEntryPayload = $entryGenerator->createBranchEntryPayload(
                                    $childFormRef,
                                    $childBranchInputs,
                                    $childEntryPayload['data']['id'],
                                    $ownerInputRef
                                );
                                $entryGenerator->createBranchEntryRow(
                                    User::find($project->created_by),
                                    $project,
                                    config('epicollect.strings.project_roles.creator'),
                                    $projectDefinition,
                                    $branchEntryPayload
                                );

                                $output->writeln("\rInserted branch entries...    ");
                            }
                        }
                    }
                }
            }
        }

        $proceed = strtolower($this->command->ask("Generate media files? (y/n)", 'n'));
        if ($proceed === 'y') {
            // Running media seeder
            $this->callWith(MediaSeeder::class, [
                'projectId' => $project->id
            ]);
        }

        // Final message
        $output->writeln("All done.");
    }

    private function getFormBranches($projectDefinition, $formIndex): array
    {
        $inputs = array_get($projectDefinition, 'data.project.forms.'.$formIndex.'.inputs');
        $branches = [];

        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $branches[$input['ref']] = $input['branch'];
            }
        }
        return $branches;
    }
}
