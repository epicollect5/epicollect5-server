<?php

namespace Tests\Traits\Eloquent;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\Entry;
use ec5\Models\Eloquent\BranchEntry;
use ec5\Models\Eloquent\EntryArchive;
use ec5\Models\Eloquent\BranchEntryArchive;
use ec5\Models\Eloquent\User;

class ArchiveEntriesTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function it_archives_entries_and_updates_stats()
    {
        $repeatCount = 10; // Number of times to repeat the test case

        for ($i = 0; $i < $repeatCount; $i++) {

            $numOfEntries = mt_rand(10, 100);
            $numOfAdditionalEntries = mt_rand(10, 100);
            $numOfBranchEntries = mt_rand(10, 100);
            $numOfAdditionalBranchEntries = mt_rand(10, 100);

            echo "Archived " . $numOfEntries . ' entries, ' . $numOfBranchEntries . ' branches, ' . ($i + 1) . ' run ' . "\n"; // Log iteration number to console
            // Create a test project (created with system admin ID)
            $project = factory(Project::class)->create([
                'created_by' => User::where('email', Config::get('testing.SUPER_ADMIN_EMAIL'))->first()['id']
            ]);
            // Create some test entries...
            $entriesToArchive = factory(Entry::class, $numOfEntries)->create([
                'project_id' => $project->id,
                'form_ref' => $project->ref . '_' . uniqid(),
                'user_id' => $project->created_by,
            ]);
            // Create additional entries that should not be archived
            $additionalProject = factory(Project::class)->create([
                'created_by' => User::where('email', Config::get('testing.SUPER_ADMIN_EMAIL'))->first()['id']
            ]);
            $additionalEntries = factory(Entry::class, $numOfAdditionalEntries)->create([
                'project_id' => $additionalProject->id,
                'form_ref' => $additionalProject->ref . '_' . uniqid(),
                'user_id' => $additionalProject->created_by,
            ]);

            //...and branch entries for the project
            foreach ($entriesToArchive as $entry) {
                factory(BranchEntry::class, $numOfBranchEntries)->create([
                    'project_id' => $project->id,
                    'form_ref' => $project->ref . '_' . uniqid(),
                    'user_id' => $project->created_by,
                    'owner_entry_id' => $entry->id //FK!
                ]);
            }

            // Create additional branch entries that should not be archived
            foreach ($additionalEntries as $additionalEntry) {
                $additionalBranchEntries = factory(BranchEntry::class, $numOfAdditionalBranchEntries)->create([
                    'project_id' => $additionalProject->id,
                    'form_ref' => $additionalProject->ref . '_' . uniqid(),
                    'user_id' => $additionalProject->created_by,
                    'owner_entry_id' => $additionalEntry->id //FK!
                ]);
            }

            // Assert that all entries are present before archiving
            $this->assertEquals($entriesToArchive->count(), Entry::where('project_id', $project->id)->count());
            $this->assertEquals($numOfEntries * $numOfBranchEntries, BranchEntry::where('project_id', $project->id)->count());

            // Run the archiveEntries function
            $result = $this->app->call('ec5\Http\Controllers\ProjectControllerBase@archiveEntries', [
                'projectId' => $project->id,
            ]);

            // Assert that the function returned true
            $this->assertTrue($result);

            // Assert that the entries and branch entries have been moved to their respective archive tables
            $this->assertEquals(0, Entry::where('project_id', $project->id)->count());
            $this->assertEquals(0, BranchEntry::where('project_id', $project->id)->count());
            $this->assertEquals($numOfEntries, EntryArchive::where('project_id', $project->id)->count());
            $this->assertEquals($numOfEntries * $numOfBranchEntries, BranchEntryArchive::where('project_id', $project->id)->count());

            // Assert that the additional entries and branch are still present
            $this->assertEquals($numOfAdditionalEntries, Entry::where('project_id', $additionalProject->id)->count());
            $this->assertEquals($numOfAdditionalEntries * $numOfAdditionalBranchEntries, BranchEntry::where('project_id', $additionalProject->id)->count());
            $this->assertEquals(0, EntryArchive::where('project_id', $additionalProject->id)->count());
            $this->assertEquals(0, BranchEntryArchive::where('project_id', $additionalProject->id)->count());
        }
    }
}
