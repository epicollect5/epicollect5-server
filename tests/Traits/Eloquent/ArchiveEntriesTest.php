<?php

namespace Tests\Traits\Eloquent;

use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\BranchEntryArchive;
use ec5\Models\Entries\Entry;
use ec5\Models\Entries\EntryArchive;
use ec5\Models\Project\Project;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ArchiveEntriesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_archives_entries_and_updates_stats()
    {
        $repeatCount = 10; // Number of times to repeat the test case

        for ($i = 0; $i < $repeatCount; $i++) {

            $numOfEntries = mt_rand(1, 5);
            $numOfAdditionalEntries = mt_rand(1, 5);
            $numOfBranchEntries = mt_rand(1, 5);
            $numOfAdditionalBranchEntries = mt_rand(1, 5);

            //echo "Archived". $numOfEntries . ' entries, ' . $numOfBranchEntries . ' branches, ' . ($i + 1) . ' run ' . "\n"; // Log iteration number to console
            // Create a test project (created with system admin ID)
            $project = factory(Project::class)->create([
                'created_by' => User::where('email', config('testing.SUPER_ADMIN_EMAIL'))->first()['id']
            ]);
            // Create some test entries...
            $entriesToArchive = factory(Entry::class, $numOfEntries)->create([
                'project_id' => $project->id,
                'form_ref' => $project->ref . '_' . uniqid(),
                'user_id' => $project->created_by,
            ]);
            // Create additional entries that should not be archived
            $additionalProject = factory(Project::class)->create([
                'created_by' => User::where('email', config('testing.SUPER_ADMIN_EMAIL'))->first()['id']
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
            $result = $this->app->call('ec5\Http\Controllers\Web\Project\ProjectDeleteEntriesController@archiveEntries', [
                'projectId' => $project->id,
                'projectRef' => $project->ref
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
