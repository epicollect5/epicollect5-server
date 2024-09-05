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

        // Get the console output instance
        $output = $this->command->getOutput();

        // Loop to insert the specified number of entries
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
            $output->write("\rInserted $num entries...    ");
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
}
