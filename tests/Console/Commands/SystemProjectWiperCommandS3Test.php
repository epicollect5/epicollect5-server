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

class SystemProjectWiperCommandS3Test extends TestCase
{
    use DatabaseTransactions;

    private int $numOfEntries = 10;
    private string $originalName = 'Test Project';
    private Project $project;


    public function setUp(): void
    {
        parent::setUp();
        $this->overrideStorageDriver('s3');

        // 1. Create Project
        $projectRef = Generators::projectRef();
        $this->project = factory(Project::class)->create([
            'status' => config('epicollect.strings.project_status.archived'),
            'ref' => $projectRef,
            'slug' => Str::slug($this->originalName, '-'),
        ]);

        // 2. Create Project Structure and Stats
        factory(ProjectStats::class)->create(['project_id' => $this->project->id]);
        factory(ProjectStructure::class)->create(
            [
                'project_id' => $this->project->id,
                'project_definition' => json_encode(['project' => ['name' => $this->originalName]]),
                'updated_at' => now(),
            ]
        );

        // 3. Create Entries and Branch Entries
        for ($i = 0; $i < $this->numOfEntries; $i++) {
            $entry = factory(Entry::class)->create(['project_id' => $this->project->id]);
            factory(BranchEntry::class)->create([
                'project_id' => $this->project->id,
                'owner_entry_id' => $entry->id
            ]);
        }
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
        $this->project->status = 'active';
        $this->project->save();

        $this->artisan('system:project-wipe', ['--project-id' => $this->project->id])
            ->expectsOutputToContain("Project must be archived before erasing.")
            ->assertExitCode(defined('Command::FAILURE') ? Command::FAILURE : 1);
    }

    public function test_it_fails_on_incorrect_confirmation_name()
    {
        $entriesCountBefore = Entry::count();
        $branchEntriesCountBefore = BranchEntry::count();
        $wrongName = 'Wrong Name';

        $this->artisan('system:project-wipe', ['--project-id' => $this->project->id])
            ->expectsQuestion("To confirm, type the project’s original name: $this->originalName", $wrongName)
            ->expectsOutputToContain("Confirmation name does not match. Aborting.")
            ->assertExitCode(defined('Command::FAILURE') ? Command::FAILURE : 1);

        // Assert data still exists
        $this->assertDatabaseHas('projects', ['id' => $this->project->id]);
        $this->assertDatabaseCount('entries', $entriesCountBefore);
        $this->assertDatabaseCount('branch_entries', $branchEntriesCountBefore);
    }

    public function test_it_runs_in_dry_run_mode_and_lists_deletions()
    {
        $entriesCountBefore = Entry::count();
        $branchEntriesCountBefore = BranchEntry::count();

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
            Storage::disk($disk)->put($this->project->ref . '/file1_' . time().$fileExtension, 'content');
            Storage::disk($disk)->put($this->project->ref . '/file2_' . time().$fileExtension, 'content');

        }

        // 3. Run Command in Dry-Run
        $command = $this->artisan('system:project-wipe', [
            '--project-id' => $this->project->id,
            '--dry-run' => true,
        ])
            ->expectsQuestion("To confirm, type the project’s original name: $this->originalName", $this->originalName);

        // 4. Assert Output
        $command->expectsOutputToContain("[DRY-RUN] Listing what would be deleted...");
        $command->expectsOutputToContain("Disk photo/{$this->project->ref}: 2 files would be deleted");
        $command->expectsOutputToContain("Disk audio/{$this->project->ref}: 2 files would be deleted");
        $command->expectsOutputToContain("Disk video/{$this->project->ref}: 2 files would be deleted");
        $command->expectsOutputToContain("Entries: $this->numOfEntries rows would be deleted, and related $this->numOfEntries branch entries.");
        $command->expectsOutputToContain("Dry-run complete. No data was deleted.");
        $command->assertExitCode(defined('Command::SUCCESS') ? Command::SUCCESS : 0);

        // 5. Assert data still exists
        $this->assertDatabaseHas('projects', ['id' => $this->project->id]);
        $this->assertDatabaseCount('entries', $entriesCountBefore);
        $this->assertDatabaseCount('branch_entries', $branchEntriesCountBefore);
        foreach ($mediaDisks as $disk) {
            $this->assertCount(2, Storage::disk($disk)->allFiles($this->project->ref));
        }
    }

    public function test_it_successfully_wipes_an_archived_project()
    {
        $preWipeEntriesCount = Entry::count();
        $preWipeBranchEntriesCount = BranchEntry::count();
        $preWipeProjectCount = Project::count();
        $preWipeProjectStructureCount = DB::table('project_structures')->count();
        $preWipeProjectStatsCount = DB::table('project_stats')->count();


        // 1. Setup Storage (real, end to end test)
        $mediaDisks = config('epicollect.media.entries_deletable');
        $fileExtension = '.jpg';

        foreach ($mediaDisks as $disk) {
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
            Storage::disk($disk)->put($this->project->ref . '/file1_' . time().$fileExtension, 'content');
            Storage::disk($disk)->put($this->project->ref . '/file2_' . time().$fileExtension, 'content');
        }

        // NOTE: You would need to mock the internal methods called by eraseProject
        // (removeMediaChunk, removeEntriesChunk, removeProject) from the ProjectWiper trait,
        // especially to control the chunking loop, unless you allow the test to interact
        // with the database/storage directly and ensure chunking settings are low for testing.
        // For simplicity, we assume the chunking in the trait is tested separately and the
        // feature test focuses on the command flow and final state.

        // 3. Run Command
        $this->artisan('system:project-wipe', ['--project-id' => $this->project->id])
            ->expectsQuestion("To confirm, type the project’s original name: $this->originalName", $this->originalName)
            ->expectsOutputToContain("Project erased successfully.")
            ->assertExitCode(defined('Command::SUCCESS') ? Command::SUCCESS : 0);

        // 4. Assert data is deleted
        $this->assertDatabaseMissing('projects', ['id' => $this->project->id]);
        $this->assertDatabaseCount('entries', $preWipeEntriesCount - $this->numOfEntries);
        $this->assertDatabaseCount('branch_entries', $preWipeBranchEntriesCount - $this->numOfEntries);
        $this->assertDatabaseMissing('project_structures', ['project_id' => $this->project->id]);

        $this->assertDatabaseCount('project_stats', $preWipeProjectStatsCount - 1);
        $this->assertDatabaseCount('projects', $preWipeProjectCount - 1);
        $this->assertDatabaseCount('project_structures', $preWipeProjectStructureCount - 1);

        // 5. Assert media is deleted (whole directory is deleted when `removeMediaChunk` is called correctly)
        foreach ($mediaDisks as $disk) {
            $this->assertCount(0, Storage::disk($disk)->allFiles($this->project->ref));
        }
    }
}
