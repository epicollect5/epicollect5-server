<?php

namespace Tests\Commands;

use Carbon\Carbon;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\BranchEntryJson;
use ec5\Models\Entries\Entry;
use ec5\Models\Entries\EntryJson;
use ec5\Models\Project\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class SystemMigrateEntriesJsonTest extends TestCase
{
    use DatabaseTransactions;

    protected string $srcTableEntries = 'entries';
    protected string $dstTableEntriesJson = 'entries_json';
    protected string $srcTableBranchEntries = 'branch_entries';
    protected string $dstTableBranchEntriesJson = 'branch_entries_json';
    protected Project $project;
    private array $testEntries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = factory(Project::class)->create();
        $this->testEntries = [
            [
                'uuid' => Uuid::uuid4()->toString(),
                'entry_data' => ['foo' => 'bar'],
                'geo_json_data' => ['lat' => 1],
                'branches' => [
                    [
                        'uuid' => Uuid::uuid4()->toString(),
                        'entry_data' => ['branch1' => 'data1'],
                        'geo_json_data' => ['branch_lat' => 2]
                    ],
                    [
                        'uuid' => Uuid::uuid4()->toString(),
                        'entry_data' => ['branch2' => 'data2'],
                        'geo_json_data' => null
                    ]
                ]
            ],
            [
                'uuid' => Uuid::uuid4()->toString(),
                'entry_data' => ['baz' => 'qux'],
                'geo_json_data' => null,
                'branches' => [
                    [
                        'uuid' => Uuid::uuid4()->toString(),
                        'entry_data' => ['branch3' => 'data3'],
                        'geo_json_data' => ['branch_lat' => 3]
                    ]
                ]
            ]
        ];

        // Insert entries
        foreach ($this->testEntries as &$testEntry) {
            $testEntry['id'] = DB::table($this->srcTableEntries)->insertGetId([
                'project_id' => $this->project->id,
                'uuid' => $testEntry['uuid'],
                'entry_data' => json_encode($testEntry['entry_data']),
                'geo_json_data' => $testEntry['geo_json_data'] !== null
                    ? json_encode($testEntry['geo_json_data'])
                    : null
            ]);

            // Insert branches for this entry
            foreach ($testEntry['branches'] as &$branch) {
                $branch['id'] = DB::table($this->srcTableBranchEntries)->insertGetId([
                    'project_id' => $this->project->id,
                    'uuid' => $branch['uuid'],
                    'owner_entry_id' => $testEntry['id'],
                    'owner_uuid' => $testEntry['uuid'],
                    'entry_data' => json_encode($branch['entry_data']),
                    'geo_json_data' => $branch['geo_json_data'] !== null
                        ? json_encode($branch['geo_json_data'])
                        : null
                ]);
            }
            unset($branch);
        }
        unset($testEntry);
    }


    public function test_it_does_a_dry_run_without_changing_anything()
    {
        $this->artisan('system:migrate-entries-json --dry-run')
            ->expectsOutput('🔍 DRY RUN MODE - No changes will be made to the database')
            ->assertExitCode(0);

        // Assert nothing changed
        $this->assertEquals(sizeof($this->testEntries), DB::table($this->srcTableEntries)->whereNotNull('entry_data')->count());
        $this->assertEquals(
            0,
            DB::table($this->dstTableEntriesJson)
                ->where('project_id', $this->project->id)
                ->count()
        );
    }

    public function test_it_migrates_and_nullifies_entries_when_checksums_match()
    {
        // BEFORE MIGRATION: Assert Entry models return entry_data and geo_json_data as arrays
        foreach ($this->testEntries as $testEntry) {
            $entry = Entry::find($testEntry['id']);

            $this->assertNotNull($entry, "Entry {$testEntry['id']} should exist");
            $this->assertEquals($testEntry['entry_data'], json_decode($entry->entry_data, true));

            if ($testEntry['geo_json_data'] !== null) {
                $this->assertEquals($testEntry['geo_json_data'], json_decode($entry->geo_json_data, true));
            } else {
                $this->assertNull($entry->geo_json_data);
            }
        }

        // Run migration
        $this->artisan('system:migrate-entries-json --limit=10')
            ->expectsOutputToContain('Migrating')
            ->assertExitCode(0);

        // Entries should be copied
        $this->assertEquals(2, DB::table($this->dstTableEntriesJson)
            ->where('project_id', $this->project->id)
            ->count());

        // Source should be nullified
        $this->assertEquals(0, DB::table($this->srcTableEntries)
            ->whereNotNull('entry_data')
            ->where('project_id', $this->project->id)
            ->count());

        // AFTER MIGRATION: Assert Entry models still return entry_data and geo_json_data as arrays
        foreach ($this->testEntries as $testEntry) {
            $entry = Entry::find($testEntry['id']);

            $this->assertNotNull($entry, "Entry {$testEntry['id']} should exist");
            $this->assertEquals($testEntry['entry_data'], json_decode($entry->entry_data, true));

            if ($testEntry['geo_json_data'] !== null) {
                $this->assertEquals($testEntry['geo_json_data'], json_decode($entry->geo_json_data, true));
            } else {
                $this->assertNull($entry->geo_json_data);
            }
        }

        // Assert destination table has correct data
        foreach ($this->testEntries as $testEntry) {
            $dst = DB::table($this->dstTableEntriesJson)->where('entry_id', $testEntry['id'])->first();
            $this->assertNotNull($dst, "Destination entry {$testEntry['id']} should exist");

            $this->assertEquals(
                json_decode($dst->entry_data, true),
                $testEntry['entry_data'],
                "Entry data mismatch for entry {$testEntry['id']}"
            );

            $this->assertEquals(
                $dst->geo_json_data !== null ? json_decode($dst->geo_json_data, true) : null,
                $testEntry['geo_json_data'],
                "Geo JSON data mismatch for entry {$testEntry['id']}"
            );
        }
    }

    public function test_it_migrates_and_nullifies_branch_entries_when_checksums_match()
    {
        // BEFORE MIGRATION: Assert BranchEntry models return entry_data and geo_json_data as arrays
        foreach ($this->testEntries as $testEntry) {
            foreach ($testEntry['branches'] as $branch) {
                $branchEntry = BranchEntry::where('id', $branch['id'])->first();

                $this->assertNotNull($branchEntry, "Branch entry {$branch['id']} should exist");
                $this->assertEquals($branch['entry_data'], json_decode($branchEntry->entry_data, true));
                $this->assertNotNull($branchEntry->entry_data);

                if ($branch['geo_json_data'] !== null) {
                    $this->assertEquals($branch['geo_json_data'], json_decode($branchEntry->geo_json_data, true));
                } else {
                    $this->assertNull($branchEntry->geo_json_data);
                }

                // Verify relationships
                $this->assertEquals($testEntry['id'], $branchEntry->owner_entry_id);
                $this->assertEquals($testEntry['uuid'], $branchEntry->owner_uuid);
            }
        }

        // Run migration
        $this->artisan('system:migrate-entries-json --branch --limit=10')
            ->expectsOutputToContain('Migrating')
            ->assertExitCode(0);

        // Count total branch entries supposed to be migrated
        $totalBranches = array_sum(array_map(fn ($e) => count($e['branches']), $this->testEntries));

        // Branch entries should be copied
        $this->assertEquals(
            $totalBranches,
            DB::table($this->dstTableBranchEntriesJson)
                ->where('project_id', $this->project->id)
                ->count()
        );

        // Source should be nullified
        $this->assertEquals(
            0,
            DB::table($this->srcTableBranchEntries)
                ->whereNotNull('entry_data')
                ->where('project_id', $this->project->id)
                ->count()
        );

        // AFTER MIGRATION: Assert BranchEntry models still return entry_data and geo_json_data as arrays
        foreach ($this->testEntries as $testEntry) {
            foreach ($testEntry['branches'] as $branch) {
                $branchEntry = BranchEntry::find($branch['id']);

                $this->assertNotNull($branchEntry, "Branch entry {$branch['id']} should exist");
                $this->assertEquals($branch['entry_data'], json_decode($branchEntry->entry_data, true));
                $this->assertNotNull($branchEntry->entry_data);

                if ($branch['geo_json_data'] !== null) {
                    $this->assertEquals($branch['geo_json_data'], json_decode($branchEntry->geo_json_data, true));
                } else {
                    $this->assertNull($branchEntry->geo_json_data);
                }
            }
        }

        // Assert destination table has correct data
        foreach ($this->testEntries as $testEntry) {
            foreach ($testEntry['branches'] as $branch) {
                $dstBranch = DB::table($this->dstTableBranchEntriesJson)->where('entry_id', $branch['id'])->first();
                $this->assertNotNull($dstBranch, "Destination branch entry {$branch['id']} should exist");

                $this->assertEquals(
                    json_decode($dstBranch->entry_data, true),
                    $branch['entry_data'],
                    "Branch entry data mismatch for branch {$branch['id']}"
                );

                $this->assertEquals(
                    $dstBranch->geo_json_data !== null ? json_decode($dstBranch->geo_json_data, true) : null,
                    $branch['geo_json_data'],
                    "Branch geo JSON data mismatch for branch {$branch['id']}"
                );
            }
        }
    }

    public function test_migrate_entries_filtered_by_year()
    {
        // Create a 2024 entry
        $entry2024 = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'entry_data' => json_encode(['foo' => 'bar']),
            'geo_json_data' => json_encode(['lat' => 1]),
            'created_at' => Carbon::create(2024, 1, 1),
            'uploaded_at' => Carbon::create(2024, 1, 1)
        ]);

        // Create a 2023 entry
        $entry2023 = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'entry_data' => json_encode(['foo' => 'bar']),
            'geo_json_data' => json_encode(['lat' => 1]),
            'created_at' => Carbon::create(2023, 1, 1),
            'uploaded_at' => Carbon::create(2023, 1, 1)
        ]);


        // Run migration filtered by year 2024
        $this->artisan('system:migrate-entries-json', ['--year' => 2024])
            ->assertExitCode(0);

        // Assert only 2024 entry was migrated
        $this->assertDatabaseHas('entries_json', ['entry_id' => $entry2024->id]);
        $this->assertDatabaseMissing('entries_json', ['entry_id' => $entry2023->id]);

        //assert entry_data and geo_json_data are nullified in source table
        // IMP:
        //  Do NOT use the Entry model here.
        //  Entry has a custom accessor that transparently falls back to entries_json,
        //  so reading $entry->entry_data would return non-null even when the source
        //  column has been correctly nullified by the migration.
        //  Always assert directly against the database table for migration tests.
        $this->assertDatabaseHas('entries', [
            'id' => $entry2024->id,
            'entry_data' => null,
            'geo_json_data' => null,
        ]);
        $src2023 = DB::table('entries')->where('id', $entry2023->id)->first();

        $this->assertNotNull($src2023->entry_data);
        $this->assertEquals(['foo' => 'bar'], json_decode($src2023->entry_data, true));

        $this->assertNotNull($src2023->geo_json_data);
        $this->assertEquals(['lat' => 1], json_decode($src2023->geo_json_data, true));



        $this->assertNotNull(EntryJson::where('entry_id', $entry2024->id)->first()->entry_data);
        $this->assertNotNull(EntryJson::where('entry_id', $entry2024->id)->first()->geo_json_data);
        $this->assertNull(EntryJson::where('entry_id', $entry2023->id)->first());
        $this->assertNull(EntryJson::where('entry_id', $entry2023->id)->first());
    }

    public function test_migrate_branch_entries_filtered_by_year()
    {
        $parentEntry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
        ]);

        // Create a 2024 entry
        $branchEntry2024 = factory(BranchEntry::class)->create([
            'project_id' => $this->project->id,
            'owner_entry_id' => $parentEntry->id,
            'owner_uuid' => $parentEntry->uuid,
            'entry_data' => json_encode(['foo' => 'bar']),
            'geo_json_data' => json_encode(['lat' => 1]),
            'created_at' => Carbon::create(2024, 1, 1),
            'uploaded_at' => Carbon::create(2024, 1, 1)
        ]);

        // Create a 2023 entry
        $branchEntry2023 = factory(BranchEntry::class)->create([
            'project_id' => $this->project->id,
            'owner_entry_id' => $parentEntry->id,
            'owner_uuid' => $parentEntry->uuid,
            'entry_data' => json_encode(['foo' => 'bar']),
            'geo_json_data' => json_encode(['lat' => 1]),
            'created_at' => Carbon::create(2023, 1, 1),
            'uploaded_at' => Carbon::create(2023, 1, 1)
        ]);


        // Run migration filtered by year 2024
        $this->artisan('system:migrate-entries-json', ['--year' => 2024, '--branch' => true])
            ->assertExitCode(0);

        //assert entry_data and geo_json_data are nullified in source table
        // IMP:
        //  Do NOT use the Entry model here.
        //  Entry has a custom accessor that transparently falls back to entries_json,
        //  so reading $entry->entry_data would return non-null even when the source
        //  column has been correctly nullified by the migration.
        //  Always assert directly against the database table for migration tests.
        $this->assertDatabaseHas('branch_entries', [
            'id' => $branchEntry2024->id,
            'entry_data' => null,
            'geo_json_data' => null,
        ]);
        $src2023 = DB::table('branch_entries')->where('id', $branchEntry2023->id)->first();

        $this->assertNotNull($src2023->entry_data);
        $this->assertEquals(['foo' => 'bar'], json_decode($src2023->entry_data, true));

        $this->assertNotNull($src2023->geo_json_data);
        $this->assertEquals(['lat' => 1], json_decode($src2023->geo_json_data, true));


        $this->assertNotNull(BranchEntryJson::where('entry_id', $branchEntry2024->id)->first()->entry_data);
        $this->assertNotNull(BranchEntryJson::where('entry_id', $branchEntry2024->id)->first()->geo_json_data);
        $this->assertNull(BranchEntryJson::where('entry_id', $branchEntry2023->id)->first());
        $this->assertNull(BranchEntryJson::where('entry_id', $branchEntry2023->id)->first());
    }

    public function test_migrate_entries_respects_limit()
    {
        // Create 3 entries for this project
        factory(Entry::class, 3)->create([
            'project_id' => $this->project->id,
            'entry_data' => json_encode(['foo' => 'bar']),
            'geo_json_data' => json_encode(['lat' => 1]),
        ]);

        $entriesCountBefore = DB::table($this->srcTableEntries)
            ->where('project_id', $this->project->id)
            ->whereNotNull('entry_data')
            ->count();

        // Run migration with limit = 1
        $this->artisan('system:migrate-entries-json', [
            '--project' => $this->project->id,
            '--limit' => 1,
        ])->assertExitCode(0);

        // Only 1 entry should be migrated
        $this->assertEquals(
            1,
            DB::table($this->dstTableEntriesJson)
                ->where('project_id', $this->project->id)
                ->count()
        );

        // Source table should still contain $entriesCountBefore - 1 non-null entries
        $this->assertEquals(
            $entriesCountBefore - 1,
            DB::table($this->srcTableEntries)
                ->where('project_id', $this->project->id)
                ->whereNotNull('entry_data')
                ->count()
        );
    }

    public function test_migrate_branch_entries_respects_limit()
    {
        // Create a parent entry
        $parentEntry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
        ]);

        // Create 3 branch entries for this project
        factory(BranchEntry::class, 3)->create([
            'project_id' => $this->project->id,
            'owner_entry_id' => $parentEntry->id,
            'owner_uuid' => $parentEntry->uuid,
            'entry_data' => json_encode(['foo' => 'bar']),
            'geo_json_data' => json_encode(['lat' => 1]),
        ]);

        $branchEntriesCountBefore = DB::table($this->srcTableBranchEntries)
            ->where('project_id', $this->project->id)
            ->whereNotNull('entry_data')
            ->count();

        // Run migration with limit = 1
        $this->artisan('system:migrate-entries-json', [
            '--project' => $this->project->id,
            '--branch' => true,
            '--limit' => 1,
        ])->assertExitCode(0);

        // Only 1 entry should be migrated
        $this->assertEquals(
            1,
            DB::table($this->dstTableBranchEntriesJson)
                ->where('project_id', $this->project->id)
                ->count()
        );

        // Source table should still contain $entriesCountBefore - 1 non-null entries
        $this->assertEquals(
            $branchEntriesCountBefore - 1,
            DB::table($this->srcTableBranchEntries)
                ->where('project_id', $this->project->id)
                ->whereNotNull('entry_data')
                ->count()
        );
    }


}
