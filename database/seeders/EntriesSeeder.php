<?php

namespace Database\Seeders;

use App;
use ec5\Libraries\Generators\EntryGenerator;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Traits\Eloquent\Remover;
use Illuminate\Database\Seeder;
use Throwable;

class EntriesSeeder extends Seeder
{
    use Remover;

    /**
     * Seeds the database with entries, including parent, branch, and child entries, for a given project.
     *
     * This seeder runs only in non-production environments. It prompts the user for a project ID and the number of entries to create,
     * then verifies the project and, if confirmed, optionally deletes any existing entries along with their media files.
     * The seeder retrieves the project structure and non-viewer user roles to assign a valid creator for each entry.
     * It generates parent entries with associated branch entries, and for additional forms in the project,
     * creates corresponding child entries (and branch entries) based on the project definition.
     *
     * Usage:
     *   php artisan db:seed --class=EntriesSeeder
     *   php artisan seed:entries
     *
     * @throws Throwable
     */
    public function run(): void
    {

        // Increase memory limit and execution time
        ini_set('memory_limit', '5120M'); // 5GB
        ini_set('max_execution_time', '0'); // Unlimited execution time

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

        if (Entry::where('project_id', $project->id)->exists()) {
            $proceed = strtolower($this->command->ask("Delete existing entries? (y/n)", 'n'));
            if ($proceed === 'y') {
                // Delete entries in chunks
                Entry::where('project_id', $project->id)
                    ->lazyById(1000)
                    ->each(function ($entry) {
                        // Get the console output instance
                        $output = $this->command->getOutput();
                        $output->writeln("\rProcessing entries deletion ...    ");
                        $entry->delete();
                    });
                //delete all media files
                $this->removeAllTheEntriesMediaFolders($project->ref);
            }
        }

        $projectStructure = ProjectStructure::where('project_id', $project->id)->first();
        $projectRolesIDs = ProjectRole::where('project_id', $project->id)
            ->where('role', '<>', config('epicollect.strings.project_roles.viewer'))
            ->pluck('user_id')
            ->toArray();
        $projectDefinition = ['data' => json_decode($projectStructure->project_definition, true)];
        $entryGenerator = new EntryGenerator($projectDefinition);

        $formRef = array_get($projectDefinition, 'data.project.forms.0.ref');
        //get any branch inputs
        $branches = $this->getFormBranches($projectDefinition, 0);

        // Get the console output instance
        $output = $this->command->getOutput();

        // Cache users to avoid multiple queries
        $projectUsers = User::whereIn('id', $projectRolesIDs)->get()->keyBy('id');

        $batchSize = 1000;

        // Loop to insert the specified number of entries in batches
        for ($batchStart = 0; $batchStart < $numOfEntries; $batchStart += $batchSize) {
            $batchEnd = min($batchStart + $batchSize, $numOfEntries);
            $output->writeln("\rProcessing entries $batchStart to $batchEnd...    ");

            for ($i = $batchStart; $i < $batchEnd; $i++) {
                // Log memory usage before creating entry payload
                // $output->writeln("Memory usage before creating parent entry payload: " . Common::formatBytes(memory_get_usage()));

                $entryPayload = $entryGenerator->createParentEntryPayload($formRef);

                // Log memory usage after creating entry payload
                // $output->writeln("Memory usage after creating parent entry payload: " . Common::formatBytes(memory_get_usage()));

                $randomUserId = $projectRolesIDs[array_rand($projectRolesIDs)];
                $entryGenerator->createParentEntryRow(
                    $projectUsers[$randomUserId], // assign random user from cached users
                    $project,
                    config('epicollect.strings.project_roles.creator'),
                    $projectDefinition,
                    $entryPayload
                );

                // Show progress
                $num = $i + 1;
                $output->write("\rInserted $num parent entries...    ");

                // Log memory usage after inserting parent entry
                //$output->writeln("Memory usage after inserting parent entry $num: " . Common::formatBytes(memory_get_usage()));

                // if any branch, generate x branch entries
                $numOfBranchEntries = 1;
                $this->insertBranches($numOfBranchEntries, $branches, $entryGenerator, $formRef, $entryPayload['data']['id'], $project, $projectDefinition, $output);

                // Manually trigger garbage collection
                gc_collect_cycles();

                // Log memory usage after garbage collection
                //$output->writeln("Memory usage after garbage collection: " . Common::formatBytes(memory_get_usage()));
            }

            // Clear entry generator and cached data
            unset($entryPayload);
            unset($entryGenerator);

            // Reinitialize entry generator to free up memory
            $entryGenerator = new EntryGenerator($projectDefinition);

            // Manually trigger garbage collection
            gc_collect_cycles();

            // Log memory usage after batch processing and cleanup
            //$output->writeln("Memory usage after batch processing and cleanup: " . Common::formatBytes(memory_get_usage()));
        }


        //loop all the child forms, if any
        $forms = array_get($projectDefinition, 'data.project.forms');
        foreach ($forms as $formIndex => $form) {
            if ($formIndex > 0) {

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
                        $this->insertBranches($numOfChildBranchEntries, $childBranches, $entryGenerator, $childFormRef, $childEntryPayload['data']['id'], $project, $projectDefinition, $output);
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

    /**
     * @throws Throwable
     */
    private function insertBranches(int $numOfBranchEntries, array $branches, EntryGenerator $entryGenerator, mixed $formRef, $id, $project, array $projectDefinition, $output): void
    {
        for ($j = 0; $j < $numOfBranchEntries; $j++) {
            foreach ($branches as $ownerInputRef => $branchInputs) {
                $branchEntryPayload = $entryGenerator->createBranchEntryPayload(
                    $formRef,
                    $branchInputs,
                    $id,
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
        //  return array($j, $ownerInputRef, $branchEntryPayload);
    }
}
