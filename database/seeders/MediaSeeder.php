<?php

namespace Database\Seeders;

use App;
use ec5\Libraries\Generators\MediaGenerator;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStructure;
use Illuminate\Database\Seeder;

class MediaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * imp: php artisan db:seed --class=MediaSeeder
     * imp: php artisan seed:media
     *
     * @param null $projectId
     * @return void
     */
    public function run($projectId = null): void
    {
        if (App::environment('production')) {
            $this->command->info('Skipping seeder in production environment.');
            return;
        }
        $skipProjectNameConfirm = false;
        if ($projectId === null) {
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
        if (!$skipProjectNameConfirm) {
            $proceed = strtolower($this->command->ask("The project name is '$projectName'. Proceed? (y/n)", 'n'));

            if ($proceed !== 'y') {
                $this->command->warn('Operation aborted.');
                return;
            }
        }

        $projectStructure = ProjectStructure::where('project_id', $project->id)->first();
        $projectDefinition = ['data' => json_decode($projectStructure->project_definition, true)];
        $forms = $projectDefinition['data']['project']['forms'];
        $entries = Entry::where('project_id', $project->id)->lazyById();
        $branchEntries = BranchEntry::where('project_id', $project->id)->lazyById();

        if ($entries->first() === null) {
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
                if ($type === config('epicollect.strings.inputs_type.branch')) {
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

                        //get all media questions in nested group(s)
                        if ($type === config('epicollect.strings.inputs_type.group')) {
                            $nestedGroupInputs = $branchInput['group'];
                            foreach ($nestedGroupInputs as $nestedGroupInput) {
                                $type = $nestedGroupInput['type'];

                                if (in_array($type, [
                                    config('epicollect.strings.inputs_type.audio'),
                                    config('epicollect.strings.inputs_type.photo'),
                                    config('epicollect.strings.inputs_type.video')
                                ])) {
                                    $mediaQuestions[$nestedGroupInput['ref']] = $type;
                                }
                            }
                        }
                    }
                }

                //get all media questions in groups
                if ($type === config('epicollect.strings.inputs_type.group')) {
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
        $totalEntries = Entry::where('project_id', $project->id)->count();
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

                    list($audioCount, $videoCount, $photoCount) = $this->generateAndStoreMediaFiles($mediaQuestions[$ref], $project, $filename, $audioCount, $videoCount, $photoCount);
                }
            }

            // Increment processed entries count
            $processedEntries++;

            // Show progress after processing each entry
            $percentage = ($processedEntries / $totalEntries) * 100;
            $output->write("\rProgress: " . number_format($percentage, 2) . "% ($processedEntries of $totalEntries entries)");
            unset($answers);
            gc_collect_cycles(); // Force garbage collection
        }

        // Show entries progress
        $output->writeln("\nDone processing entries:");
        $output->writeln("Added $photoCount photo files to entries");
        $output->writeln("Added $audioCount audio files to entries");
        $output->writeln("Added $videoCount video files to entries");

        $output->writeln('...');

        if ($branchEntries->first() === null) {
            // Final message
            $output->writeln("No branch entries found for this project.");
            $output->writeln("All done.");
            return;
        }

        // Initialize counters and totals
        $totalBranchEntries = BranchEntry::where('project_id', $project->id)->count();
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

                    list($audioCount, $videoCount, $photoCount) = $this->generateAndStoreMediaFiles($mediaQuestions[$ref], $project, $filename, $audioCount, $videoCount, $photoCount);
                }
            }

            // Increment processed entries count
            $processedEntries++;

            // Show progress after processing each entry
            $percentage = ($processedEntries / $totalBranchEntries) * 100;
            $output->write("\rProgress: " . number_format($percentage, 2) . "% ($processedEntries of $totalBranchEntries branch entries)");

            unset($answers);
            gc_collect_cycles(); // Force garbage collection
        }

        // Show branches progress
        $output->writeln("\nDone processing branch entries:");
        $output->writeln("Added $photoCount photo files to branch entries");
        $output->writeln("Added $audioCount audio files to branch entries");
        $output->writeln("Added $videoCount video files to branch entries");

        // Final message
        $output->writeln("All done.");
    }

    private function generateAndStoreMediaFiles($mediaQuestions, Project $project, mixed $filename, int $audioCount, int $videoCount, int $photoCount): array
    {
        if ($mediaQuestions === config('epicollect.strings.inputs_type.audio')) {
            MediaGenerator::generateAndStoreAudioFile($project->ref, $filename);
            $audioCount++;
        }
        if ($mediaQuestions === config('epicollect.strings.inputs_type.video')) {
            MediaGenerator::generateAndStoreVideoFile($project->ref, $filename);
            $videoCount++;
        }
        if ($mediaQuestions === config('epicollect.strings.inputs_type.photo')) {
            MediaGenerator::generateAndStorePhotoFiles($project->ref, $filename);
            $photoCount++;
        }
        return array($audioCount, $videoCount, $photoCount);
    }
}
