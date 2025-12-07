<?php

namespace Tests\Console\Commands;

use ec5\Libraries\Utilities\Generators;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class SystemProjectWiperCommandTest extends TestCase
{
    use DatabaseTransactions;

    private int $numOfEntries = 10;

    /**
     * Set up a project structure for testing.
     * @param string $status
     * @param string $originalName
     * @return Project
     */
    protected function setupProject(string $status = 'archived', string $originalName = 'Test Project'): Project
    {
        // 1. Create Project
        $projectRef = Generators::projectRef();
        $project = factory(Project::class)->create([
            'status' => $status,
            'ref' => $projectRef,
            'slug' => Str::slug($originalName, '-'),
        ]);

        factory(ProjectStats::class)->create(['project_id' => $project->id]);
        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id,
                'project_definition' => json_encode(['project' => ['name' => $originalName]]),
                'updated_at' => now(),
            ]
        );

        // 2. Insert project_structure for original name lookup
        DB::table('project_structures')->insert([
            'project_id' => $project->id,

        ]);

        // 3. Create Entries and Branch Entries
        for ($i = 0; $i < $this->numOfEntries; $i++) {
            $entry = Factory(Entry::class)->create(['project_id' => $project->id]);
            Factory(BranchEntry::class)->create([
                'project_id' => $project->id,
                'owner_entry_id' => $entry->id
            ]);
        }

        return $project;
    }

    public function test_it_fails_if_project_id_option_is_missing()
    {
        $this->artisan('system:project-wipe')
            ->expectsOutputToContain('The --project-id option is required and must be a valid ID.') // Assuming Laravel validates required options
            ->assertExitCode(defined('Command::FAILURE') ? Command::FAILURE : 1);
    }

    public function test_it_fails_if_project_is_not_found()
    {
        $nonExistentId = 9999999;
        $this->artisan('system:project-wipe', ['--project-id' => $nonExistentId])
            ->expectsOutputToContain("Project not found with ID $nonExistentId")
            ->assertExitCode(defined('Command::FAILURE') ? Command::FAILURE : 1);
    }

    public function test_it_fails_if_project_is_not_archived()
    {
        $project = $this->setupProject('active');

        $this->artisan('system:project-wipe', ['--project-id' => $project->id])
            ->expectsOutputToContain("Project must be archived before erasing.")
            ->assertExitCode(defined('Command::FAILURE') ? Command::FAILURE : 1);
    }

    public function test_it_fails_on_incorrect_confirmation_name()
    {
        $project = $this->setupProject('archived');
        $originalName = 'Test Project';
        $wrongName = 'Wrong Name';

        $this->artisan('system:project-wipe', ['--project-id' => $project->id])
            ->expectsQuestion("To confirm, type the project’s original name: $originalName", $wrongName)
            ->expectsOutputToContain("Confirmation name does not match. Aborting.")
            ->assertExitCode(defined('Command::FAILURE') ? Command::FAILURE : 1);

        // Assert data still exists
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertDatabaseCount('entries', $this->numOfEntries);
        $this->assertDatabaseCount('branch_entries', $this->numOfEntries);
    }

    public function test_it_runs_in_dry_run_mode_and_lists_deletions()
    {
        // 1. Setup Project and Entries
        $project = $this->setupProject('archived');
        $originalName = 'Test Project';

        // 2. Setup Storage Fakes
        $mediaDisks = config('epicollect.media.entries_deletable');
        $fileExtension = '.jpg';

        foreach ($mediaDisks as $disk) {
            Storage::fake($disk);
            switch ($disk) {
                case 'photo':
                    $fileExtension = '.jpg';
                    break;
                case 'video':
                case 'audio':
                    $fileExtension = '.mp4';
                    break;
            }
            // Create some files in the project's ref folder
            Storage::disk($disk)->put($project->ref . '/file1_' . time().$fileExtension, 'content');
            Storage::disk($disk)->put($project->ref . '/file2_' . time().$fileExtension, 'content');

        }

        // 3. Run Command in Dry-Run
        $command = $this->artisan('system:project-wipe', [
            '--project-id' => $project->id,
            '--dry-run' => true,
        ])
            ->expectsQuestion("To confirm, type the project’s original name: $originalName", $originalName);

        // 4. Assert Output
        $command->expectsOutputToContain("[DRY-RUN] Listing what would be deleted...");
        $command->expectsOutputToContain("Disk photo/$project->ref: 2 files would be deleted");
        $command->expectsOutputToContain("Disk audio/$project->ref: 2 files would be deleted");
        $command->expectsOutputToContain("Disk video/$project->ref: 2 files would be deleted");
        $command->expectsOutputToContain("Entries: $this->numOfEntries rows would be deleted, and related $this->numOfEntries branch entries.");
        $command->expectsOutputToContain("Dry-run complete. No data was deleted.");
        $command->assertExitCode(defined('Command::SUCCESS') ? Command::SUCCESS : 0);

        // 5. Assert data still exists
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertDatabaseCount('entries', $this->numOfEntries);
        foreach ($mediaDisks as $disk) {
            $this->assertCount(2, Storage::disk($disk)->allFiles($project->ref));
        }
    }

    public function test_it_successfully_wipes_an_archived_project()
    {
        $preWipeEntriesCount = Entry::count();
        $preWipeBranchEntriesCount = BranchEntry::count();
        $preWipeProjectCount = Project::count();
        $preWipeProjectStructureCount = DB::table('project_structures')->count();
        $preWipeProjectStatsCount = DB::table('project_stats')->count();

        // 1. Setup Project and Entries
        $project = $this->setupProject('archived');
        $originalName = 'Test Project';

        // 2. Setup Storage Fakes
        $mediaDisks = config('epicollect.media.entries_deletable');
        $fileExtension = '.jpg';

        foreach ($mediaDisks as $disk) {
            //  Storage::fake($disk);
            switch ($disk) {
                case 'photo':
                    $fileExtension = '.jpg';
                    break;
                case 'video':
                case 'audio':
                    $fileExtension = '.mp4';
                    break;
            }
            // Create some files in the project's ref folder
            Storage::disk($disk)->put($project->ref . '/file1_' . time().$fileExtension, 'content');
            Storage::disk($disk)->put($project->ref . '/file2_' . time().$fileExtension, 'content');
        }

        // NOTE: You would need to mock the internal methods called by eraseProject
        // (removeMediaChunk, removeEntriesChunk, removeProject) from the ProjectWiper trait,
        // especially to control the chunking loop, unless you allow the test to interact
        // with the database/storage directly and ensure chunking settings are low for testing.
        // For simplicity, we assume the chunking in the trait is tested separately and the
        // feature test focuses on the command flow and final state.

        // 3. Run Command
        $this->artisan('system:project-wipe', ['--project-id' => $project->id])
            ->expectsQuestion("To confirm, type the project’s original name: $originalName", $originalName)
            ->expectsOutputToContain("Project erased successfully.")
            ->assertExitCode(defined('Command::SUCCESS') ? Command::SUCCESS : 0);

        // 4. Assert data is deleted
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseCount('entries', $preWipeEntriesCount);
        $this->assertDatabaseCount('branch_entries', $preWipeBranchEntriesCount);
        $this->assertDatabaseMissing('project_structures', ['project_id' => $project->id]);

        $this->assertDatabaseCount('project_stats', $preWipeProjectStatsCount);
        $this->assertDatabaseCount('projects', $preWipeProjectCount);
        $this->assertDatabaseCount('project_structures', $preWipeProjectStructureCount);

        // 5. Assert media is deleted (Storage::fake deletes the directory when `removeMediaChunk` is called correctly)
        foreach ($mediaDisks as $disk) {
            $this->assertCount(0, Storage::disk($disk)->allFiles($project->ref));
        }
    }
}
