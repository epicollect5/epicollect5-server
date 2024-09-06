<?php

namespace Database\Seeders;

use App;
use ec5\Models\Entries\BranchEntry;
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
     * imp: php artisan seed:media
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
        $branchEntries = BranchEntry::where('project_id', $project->id)->get();

        if(sizeof($entries) === 0) {
            $this->command->error('No entries found for this project.');
            return;
        }

        $mediaQuestions = [];

        foreach ($forms as $form) {
            $inputs = $form['inputs'];
            foreach ($inputs as $input) {
                $type = $input['type'];

                //get all media questions
                if (in_array($type, [
                    config('epicollect.strings.inputs_type.audio'),
                    config('epicollect.strings.inputs_type.photo'),
                    config('epicollect.strings.inputs_type.video')
                ])) {
                    $mediaQuestions[$input['ref']] = $type;
                }

                //get all media questions in branches
                if($type === config('epicollect.strings.inputs_type.branch')) {
                    $branchInputs = $input['branch'];
                    foreach ($branchInputs as $branchInput) {
                        $type = $branchInput['type'];

                        if (in_array($type, [
                            config('epicollect.strings.inputs_type.audio'),
                            config('epicollect.strings.inputs_type.photo'),
                            config('epicollect.strings.inputs_type.video')
                        ])) {
                            $mediaQuestions[$branchInput['ref']] = $type;
                        }
                    }
                }

                //get all media questions in groups
                if($type === config('epicollect.strings.inputs_type.group')) {
                    $groupInputs = $input['group'];
                    foreach ($groupInputs as $groupInput) {
                        $type = $groupInput['type'];

                        if (in_array($type, [
                            config('epicollect.strings.inputs_type.audio'),
                            config('epicollect.strings.inputs_type.photo'),
                            config('epicollect.strings.inputs_type.video')
                        ])) {
                            $mediaQuestions[$groupInput['ref']] = $type;
                        }
                    }
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

        // Show entries progress
        $output->writeln("\nDone processing entries:");
        $output->writeln("Added $photoCount photo files");
        $output->writeln("Added $audioCount audio files");
        $output->writeln("Added $videoCount video files");

        $output->writeln('...');

        if(sizeof($branchEntries) === 0) {
            // Final message
            $output->writeln("No branch entries found for this project.");
            $output->writeln("All done.");
            return;
        }

        // Initialize counters and totals
        $totalBranchEntries = count($branchEntries); // Total number of entries
        $photoCount = 0;
        $audioCount = 0;
        $videoCount = 0;

        // Track processed entries for progress calculation
        $processedEntries = 0;
        foreach ($branchEntries as $branchEntry) {
            $answers = json_decode($branchEntry->entry_data, true)['branch_entry']['answers'];
            foreach ($answers as $ref => $answer) {
                if (array_key_exists($ref, $mediaQuestions)) {
                    $filename = $answer['answer'];

                    // Calculate and display progress
                    $percentage = ($processedEntries / $totalBranchEntries) * 100;
                    $output->write("\rProgress: " . number_format($percentage, 2) . "% ($processedEntries of $totalBranchEntries branch entries)");

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
            $percentage = ($processedEntries / $totalBranchEntries) * 100;
            $output->write("\rProgress: " . number_format($percentage, 2) . "% ($processedEntries of $totalBranchEntries branch entries)");
        }

        // Show branches progress
        $output->writeln("\nDone processing branch entries:");
        $output->writeln("Added $photoCount photo files");
        $output->writeln("Added $audioCount audio files");
        $output->writeln("Added $videoCount video files");

        // Final message
        $output->writeln("All done.");
    }
}
