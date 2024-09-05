<?php

namespace Database\Seeders;

use App;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStructure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Tests\Generators\MediaGenerator;

class MediaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * imp: php artisan db:seed --class=MediaSeeder
     * imp: imp: php artisan seed:media
     *
     * @return void
     * @noinspection DuplicatedCode
     */
    public function run($projectId = null): void
    {
        if (App::environment('production')) {
            $this->command->info('Skipping seeder in production environment.');
            return;
        }
        $skipProjectNameConfirm = false;
        if($projectId === null) {
            $projectId = (int)$this->command->ask('Please enter the project ID', 7);
        } else {
            $skipProjectNameConfirm = true;
        }

        $project = Project::find($projectId);

        if ($project === null) {
            $this->command->error('Project not found.');
            return;
        }

        // Confirm project name?
        $projectName = $project->name;
        if(!$skipProjectNameConfirm) {
            $proceed = strtolower($this->command->ask("The project name is '{$projectName}'. Proceed? (y/n)", 'n'));

            if ($proceed !== 'y') {
                $this->command->warn('Operation aborted.');
                return;
            }
        }

        $projectStructure = ProjectStructure::where('project_id', $project->id)->first();
        $projectDefinition = ['data' => json_decode($projectStructure->project_definition, true)];
        $forms = $projectDefinition['data']['project']['forms'];
        $entries = Entry::where('project_id', $project->id)->get();

        if(sizeof($entries) === 0) {
            $this->command->error('No entries found for this project.');
            return;
        }

        $mediaQuestions = [];

        foreach ($forms as $form) {
            $inputs = $form['inputs'];
            foreach ($inputs as $input) {
                $type = $input['type'];

                if (in_array($type, [
                    config('epicollect.strings.inputs_type.audio'),
                    config('epicollect.strings.inputs_type.photo'),
                    config('epicollect.strings.inputs_type.video')
                ])) {
                    $mediaQuestions[$input['ref']] = $type;
                }
            }
        }

        // Get the console output instance
        $output = $this->command->getOutput();

        // Initialize counters and totals
        $totalEntries = count($entries); // Total number of entries
        $photoCount = 0;
        $audioCount = 0;
        $videoCount = 0;

        // Track processed entries for progress calculation
        $processedEntries = 0;

        foreach ($entries as $entry) {
            $answers = json_decode($entry->entry_data, true)['entry']['answers'];
            foreach ($answers as $ref => $answer) {
                if (array_key_exists($ref, $mediaQuestions)) {
                    $filename = $answer['answer'];

                    // Calculate and display progress
                    $percentage = ($processedEntries / $totalEntries) * 100;
                    $output->write("\rProgress: " . number_format($percentage, 2) . "% ($processedEntries of $totalEntries entries)");

                    if ($mediaQuestions[$ref] === config('epicollect.strings.inputs_type.audio')) {
                        MediaGenerator::generateAndStoreAudioFile($project->ref, $filename);
                        $audioCount++;
                    }
                    if ($mediaQuestions[$ref] === config('epicollect.strings.inputs_type.video')) {
                        MediaGenerator::generateAndStoreVideoFile($project->ref, $filename);
                        $videoCount++;
                    }
                    if ($mediaQuestions[$ref] === config('epicollect.strings.inputs_type.photo')) {
                        // Save entry_original format
                        $entryOriginalStream = MediaGenerator::generateEntryOriginal();
                        $entryThumbStream = MediaGenerator::generateEntryThumb($entryOriginalStream);
                        Storage::disk('entry_original')->put($project->ref . '/' . $filename, $entryOriginalStream);
                        Storage::disk('entry_thumb')->put($project->ref . '/' . $filename, $entryThumbStream);
                        $photoCount++;
                    }
                }
            }

            // Increment processed entries count
            $processedEntries++;

            // Show progress after processing each entry
            $percentage = ($processedEntries / $totalEntries) * 100;
            $output->write("\rProgress: " . number_format($percentage, 2) . "% ($processedEntries of $totalEntries entries)");
        }

        // Show final progress
        $output->writeln("\nDone processing entries:");
        $output->writeln("Added $photoCount photo files");
        $output->writeln("Added $audioCount audio files");
        $output->writeln("Added $videoCount video files");


        // Final message
        $output->writeln("All done.");
    }
}
