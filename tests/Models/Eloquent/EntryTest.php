<?php

namespace Tests\Models\Eloquent;

use Carbon\Carbon;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\Entries\Entry;
use ec5\Models\Entries\EntryJson;
use ec5\Models\Project\Project;
use ec5\Models\User\User;
use ec5\Traits\Assertions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EntryTest extends TestCase
{
    use DatabaseTransactions;
    use Assertions;

    protected User $user;
    protected Project $project;
    protected string $formRef;
    protected User $superadmin;
    protected Entry $entryModel;

    public function setUp(): void
    {
        parent::setUp();

        $this->entryModel = new Entry();
        $user = factory(User::class)->create();
        $superadmin = User::where('email', config('epicollect.setup.super_admin_user.email'))->first();
        $project = factory(Project::class)->create([
            'created_by' => $user->id
        ]);
        $formRef = Generators::formRef($project->ref);

        $entry = factory(Entry::class)->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'form_ref' => $formRef,
            'title' => 'Ciao'
        ]);

        $this->assertEquals(1, $this->entryModel->getEntriesByForm(
            $project->id,
            ['form_ref' => $formRef],
            []
        )->count());

        $this->assertEquals(1, $this->entryModel->getEntriesByForm(
            $project->id,
            [
                'form_ref' => $formRef,
                'title' => 'Ciao'
            ],
            []
        )->count());

        $this->assertEquals(0, $this->entryModel->getEntriesByForm(
            $project->id,
            [
                'form_ref' => $formRef,
                'user_id' => $superadmin->id
            ],
            []
        )->count());

        $this->project = $project;
        $this->user = $user;
        $this->superadmin = $superadmin;
        $this->formRef = $formRef;

        //remove safety check entry
        $entry->delete();

    }

    public function test_should_get_legacy_json_single_entry_by_uuid()
    {
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'form_ref' => $this->formRef,
            'entry_data' => json_encode(['foo' => 'bar']),
            'geo_json_data' => json_encode(['lat' => 1]),
            'title' => 'Ciao'
        ]);
        $entryFromDB = $this->entryModel->getEntry($this->project->id, [
            'uuid' => $entry->uuid,
            'form_ref' => $this->formRef
        ])->first();
        $this->assertEquals($entry->uuid, $entryFromDB->uuid);
        $this->assertJsonEquals($entry->entry_data, $entryFromDB->entry_data);
        $this->assertJsonEquals($entry->geo_json_data, $entryFromDB->geo_json_data);
    }

    public function test_should_get_new_json_single_entry_by_uuid()
    {
        $entry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'form_ref' => $this->formRef,
            'entry_data' => null,
            'geo_json_data' => null,
            'title' => 'Ciao'
        ]);

        factory(EntryJson::class)->create([
            'entry_id' => $entry->id,
            'project_id' => $this->project->id,
            'entry_data' => json_encode(['foo' => 'bar']),
            'geo_json_data' => json_encode(['lat' => 1])
        ]);

        $entryFromDB = $this->entryModel->getEntry($this->project->id, [
            'uuid' => $entry->uuid,
            'form_ref' => $this->formRef
        ])->first();
        $this->assertEquals($entry->uuid, $entryFromDB->uuid);
        $this->assertJsonEquals($entry->entry_data, $entryFromDB->entry_data);
        $this->assertJsonEquals($entry->geo_json_data, $entryFromDB->geo_json_data);
    }

    public function test_should_get_child_entries_for_parent_having_json_in_same_table()
    {
        $parentEntry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'form_ref' => $this->formRef,
            'entry_data' => json_encode(['foo' => 'bar']),
            'geo_json_data' => json_encode(['lat' => 1]),
            'title' => 'Ciao'
        ]);

        $childFormRef = Generators::formRef($this->project->ref);
        $childEntry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'form_ref' => $childFormRef,
            'parent_form_ref' => $this->formRef,
            'parent_uuid' => $parentEntry->uuid,
            'entry_data' => json_encode(['bin' => 'bon']),
            'geo_json_data' => json_encode(['lat' => 8]),
            'title' => 'Born Yesterday'
        ]);

        $childEntries = $this->entryModel->getChildEntriesForParent($this->project->id, [
            'parent_uuid' => $parentEntry->uuid,
            'form_ref' => $childFormRef,
            'parent_form_ref' => $this->formRef
        ]);
        $this->assertEquals(1, $childEntries->count());
        $this->assertEquals($childEntry->uuid, $childEntries->first()->uuid);
        $this->assertJsonEquals($childEntry->entry_data, $childEntries->first()->entry_data);
        $this->assertJsonEquals($childEntry->geo_json_data, $childEntries->first()->geo_json_data);
    }

    public function test_should_get_child_entries_for_parent_having_json_in_separate_table()
    {
        $parentEntry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'form_ref' => $this->formRef,
            'entry_data' => json_encode(['foo' => 'bar']),
            'geo_json_data' => json_encode(['lat' => 1]),
            'title' => 'Ciao'
        ]);

        $childFormRef = Generators::formRef($this->project->ref);
        $childEntry = factory(Entry::class)->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'form_ref' => $childFormRef,
            'parent_form_ref' => $this->formRef,
            'parent_uuid' => $parentEntry->uuid,
            'entry_data' => null,
            'geo_json_data' => null,
            'title' => 'Born Yesterday'
        ]);
        factory(EntryJson::class)->create([
            'entry_id' => $childEntry->id,
            'project_id' => $this->project->id,
            'entry_data' => json_encode(['bin' => 'bon']),
            'geo_json_data' => json_encode(['lat' => 8])
        ]);

        $childEntries = $this->entryModel->getChildEntriesForParent($this->project->id, [
            'parent_uuid' => $parentEntry->uuid,
            'form_ref' => $childFormRef,
            'parent_form_ref' => $this->formRef
        ]);
        $this->assertEquals(1, $childEntries->count());
        $this->assertEquals($childEntry->uuid, $childEntries->first()->uuid);
        $this->assertJsonEquals($childEntry->entry_data, $childEntries->first()->entry_data);
        $this->assertJsonEquals($childEntry->geo_json_data, $childEntries->first()->geo_json_data);
    }

    public function test_should_get_entries_by_form()
    {
        //create fake entries
        $numOfEntries = rand(1, 5);

        for ($i = 0; $i < $numOfEntries; $i++) {
            $entry = factory(Entry::class)->create([
                'project_id' => $this->project->id,
                'user_id' => $this->user->id,
                'form_ref' => $this->formRef,
                'entry_data' => null,
                'geo_json_data' => null
            ]);
            //add json data
            factory(EntryJson::class)->create([
                'entry_id' => $entry->id,
                'project_id' => $this->project->id,
                'entry_data' => json_encode(['foo' => 'bar']),
                'geo_json_data' => json_encode(['lat' => 1])
            ]);
        }

        $this->assertEquals($numOfEntries, $this->entryModel->getEntriesByForm(
            $this->project->id,
            [
                'form_ref' => $this->formRef
            ],
            []
        )->count());

        //assert json data is returned
        $entries = $this->entryModel->getEntriesByForm(
            $this->project->id,
            [
                'form_ref' => $this->formRef
            ],
            []
        )->get();
        foreach ($entries as $entry) {
            $this->assertNotNull($entry->entry_data);
            $this->assertNotNull($entry->geo_json_data);
            $this->assertJsonEquals($entry->entry_data, json_encode(['foo' => 'bar']));
            $this->assertJsonEquals($entry->geo_json_data, json_encode(['lat' => 1]));
        }
    }

    public function test_should_get_entries_by_form_with_legacy_json()
    {
        //create fake entries
        $numOfEntries = rand(1, 5);

        for ($i = 0; $i < $numOfEntries; $i++) {
            //create entry with json data only for even entries
            if ($i % 2 === 0) {
                $entry = factory(Entry::class)->create([
                    'project_id' => $this->project->id,
                    'user_id' => $this->user->id,
                    'form_ref' => $this->formRef,
                    'entry_data' => null,
                    'geo_json_data' => null
                ]);
                factory(EntryJson::class)->create([
                    'entry_id' => $entry->id,
                    'project_id' => $this->project->id,
                    'entry_data' => json_encode(['foo' => 'bar']),
                    'geo_json_data' => json_encode(['lat' => 1])
                ]);
            } else {
                //odd entries are legacy entries with json data in the same table
                factory(Entry::class)->create([
                    'project_id' => $this->project->id,
                    'user_id' => $this->user->id,
                    'form_ref' => $this->formRef,
                    'entry_data' => json_encode(['foo' => 'bar']),
                    'geo_json_data' => json_encode(['lat' => 1])
                ]);
            }
        }

        $this->assertEquals($numOfEntries, $this->entryModel->getEntriesByForm(
            $this->project->id,
            [
                'form_ref' => $this->formRef
            ],
            []
        )->count());

        //assert json data is returned
        $entries = $this->entryModel->getEntriesByForm(
            $this->project->id,
            [
                'form_ref' => $this->formRef
            ],
            []
        )->get();
        foreach ($entries as $entry) {
            $this->assertNotNull($entry->entry_data);
            $this->assertNotNull($entry->geo_json_data);
            $this->assertJsonEquals($entry->entry_data, json_encode(['foo' => 'bar']));
            $this->assertJsonEquals($entry->geo_json_data, json_encode(['lat' => 1]));
        }
    }

    public function test_should_get_entries_by_form_and_title()
    {
        //create fake entries
        $numOfEntries = rand(1, 5);

        for ($i = 0; $i < $numOfEntries; $i++) {
            $entry = factory(Entry::class)->create([
                'project_id' => $this->project->id,
                'user_id' => $this->user->id,
                'form_ref' => $this->formRef,
                'title' => 'Ciao - ' . $i,
                'entry_data' => null,
                'geo_json_data' => null
            ]);
            factory(EntryJson::class)->create([
                'entry_id' => $entry->id,
                'project_id' => $this->project->id,
                'entry_data' => json_encode(['foo' => 'bar']),
                'geo_json_data' => json_encode(['lat' => 1])
            ]);
        }

        $this->assertEquals(1, $this->entryModel->getEntriesByForm(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'title' => 'Ciao - 0'
            ],
            []
        )->count());

        $this->assertEquals($numOfEntries, $this->entryModel->getEntriesByForm(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'title' => 'Ciao'
            ],
            []
        )->count());

        $this->assertEquals(0, $this->entryModel->getEntriesByForm(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'title' => 'Nothing'
            ],
            []
        )->count());

        //assert json data is returned
        $entries = $this->entryModel->getEntriesByForm(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'title' => 'Ciao'
            ],
            []
        )->get();
        foreach ($entries as $entry) {
            $this->assertNotNull($entry->entry_data);
            $this->assertNotNull($entry->geo_json_data);
            $this->assertJsonEquals($entry->entry_data, json_encode(['foo' => 'bar']));
            $this->assertJsonEquals($entry->geo_json_data, json_encode(['lat' => 1]));
        }
    }

    public function test_should_get_entries_by_user()
    {
        //create fake entries
        $numOfEntries = rand(1, 5);

        //create a collector user for the project
        $collector = factory(User::class)->create();

        for ($i = 0; $i < $numOfEntries; $i++) {
            $entry = factory(Entry::class)->create([
                'project_id' => $this->project->id,
                'user_id' => $collector->id,
                'form_ref' => $this->formRef,
                'title' => 'Ciao - ' . $i,
                'entry_data' => null,
                'geo_json_data' => null
            ]);
            factory(EntryJson::class)->create([
                'entry_id' => $entry->id,
                'project_id' => $this->project->id,
                'entry_data' => json_encode(['foo' => 'bar']),
                'geo_json_data' => json_encode(['lat' => 1])
            ]);
        }

        $this->assertEquals(0, $this->entryModel->getEntriesByForm(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'user_id' => $this->user->id
            ],
            []
        )->count());

        $this->assertEquals(0, $this->entryModel->getEntriesByForm(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'user_id' => $this->superadmin->id
            ],
            []
        )->count());

        $this->assertEquals($numOfEntries, $this->entryModel->getEntriesByForm(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'user_id' => $collector->id
            ],
            []
        )->count());

        //assert json data is returned
        $entries = $this->entryModel->getEntriesByForm(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'user_id' => $collector->id
            ],
            []
        )->get();
        foreach ($entries as $entry) {
            $this->assertNotNull($entry->entry_data);
            $this->assertNotNull($entry->geo_json_data);
            $this->assertJsonEquals($entry->entry_data, json_encode(['foo' => 'bar']));
            $this->assertJsonEquals($entry->geo_json_data, json_encode(['lat' => 1]));
        }
    }

    public function test_should_filter_entries_by_created_at()
    {
        //create fake entries
        $numOfEntries = 8;
        //create a collector user for the project
        $collector = factory(User::class)->create();

        $year = 2016;
        for ($i = 0; $i < $numOfEntries; $i++) {
            factory(Entry::class)->create([
                'project_id' => $this->project->id,
                'user_id' => $collector->id,
                'form_ref' => $this->formRef,
                'title' => 'Ciao - ' . $i,
                'created_at' => Carbon::create($year)->toIso8601String()
            ]);
            $year++;
        }

        $this->assertEquals($numOfEntries, $this->entryModel->getEntriesByForm(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'user_id' => $collector->id
            ],
            []
        )->count());

        //filter by year (one by one)
        $year = 2016;
        for ($i = 0; $i < $numOfEntries; $i++) {
            $this->assertEquals(1, $this->entryModel->getEntriesByForm(
                $this->project->id,
                [
                    'form_ref' => $this->formRef,
                    'filter_by' => 'created_at',
                    'filter_from' => Carbon::create($year)->toIso8601String(),
                    'filter_to' => Carbon::create($year)->toIso8601String(),
                ],
                []
            )->count());
            $year++;
        }

        //filter by year (between)
        $yearFrom = 2020;
        $yearTo = 2022;
        $this->assertEquals(3, $this->entryModel->getEntriesByForm(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'filter_by' => 'created_at',
                'filter_from' => Carbon::create($yearFrom)->toIso8601String(),
                'filter_to' => Carbon::create($yearTo)->toIso8601String(),
            ],
            []
        )->count());

        //remove entries so far
        Entry::where('project_id', $this->project->id)->delete();
        $this->assertEquals(0, $this->entryModel->getEntriesByForm(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
            ],
            []
        )->count());

        //create one entry per month
        $currentYear = Carbon::now()->year;
        for ($month = 1; $month <= 12; $month++) {
            // Create a Carbon instance for the current month and year
            $createdAt = Carbon::create($currentYear, $month)->toIso8601String();
            // Create an entry for this month
            factory(Entry::class)->create([
                'project_id' => $this->project->id,
                'user_id' => $collector->id,
                'form_ref' => $this->formRef,
                'title' => 'Ciao - ' . $month, // You might want to include the month in the title
                'created_at' => $createdAt
            ]);
        }

        //assert one entry per month
        for ($month = 1; $month <= 12; $month++) {
            $this->assertEquals(1, $this->entryModel->getEntriesByForm(
                $this->project->id,
                [
                    'form_ref' => $this->formRef,
                    'filter_by' => 'created_at',
                    'filter_from' => Carbon::create($currentYear, $month)->toIso8601String(),
                    'filter_to' => Carbon::create($currentYear, $month)->toIso8601String(),
                ],
                []
            )->count());
        }
    }

    public function test_should_sort_entries_by_natural_sorting_numeric()
    {

        //create fake entries
        $numOfEntries = 50;
        //create a collector user for the project
        $collector = factory(User::class)->create();
        for ($i = 0; $i < $numOfEntries; $i++) {
            factory(Entry::class)->create([
                'project_id' => $this->project->id,
                'user_id' => $collector->id,
                'form_ref' => $this->formRef,
                'title' => 'Ciao - ' . $i
            ]);
        }

        // Fetch entries with natural sorting applied ASC
        $entries = $this->entryModel->getEntriesByForm($this->project->id, [
            'form_ref' => $this->formRef,
            'sort_by' => 'title',
            'sort_order' => 'ASC'
        ])->get();

        // Assertions to test the natural sort order
        for ($i = 0; $i < $numOfEntries; $i++) {
            $this->assertEquals('Ciao - ' . $i, $entries[$i]->title);
        }

        // Fetch entries with natural sorting applied ASC
        $entries = $this->entryModel->getEntriesByForm($this->project->id, [
            'form_ref' => $this->formRef,
            'sort_by' => 'title',
            //   'sort_order' => 'DESC' //default
        ])->get();

        // Assert in reverse order for DESC natural sorting
        for ($i = 0; $i < $numOfEntries; $i++) {
            $this->assertEquals('Ciao - ' . ($numOfEntries - 1 - $i), $entries[$i]->title);
        }

        // Fetch entries with natural sorting applied ASC
        $entries = $this->entryModel->getEntriesByForm($this->project->id, [
            'form_ref' => $this->formRef,
            'sort_by' => 'title',
            'sort_order' => 'DESC' //default
        ])->get();

        // Assert in reverse order for DESC natural sorting
        for ($i = 0; $i < $numOfEntries; $i++) {
            $this->assertEquals('Ciao - ' . ($numOfEntries - 1 - $i), $entries[$i]->title);
        }
    }

    public function test_should_sort_entries_by_natural_sorting_accented_chars()
    {
        // Create sample entries with titles containing accented letters
        $titles = [
            'Café',
            'Résumé',
            'Hôtel',
        ];

        foreach ($titles as $title) {
            factory(Entry::class)->create(['title' => $title]);
        }

        // Fetch entries with natural sorting applied
        $entries = $this->entryModel->getEntriesByForm($this->project->id, [
            'form_ref' => $this->formRef,
            'sort_by' => 'title',
            'sort_order' => 'ASC'
        ])->get();

        // Define expected order after natural sorting with accents
        $expectedOrder = [
            'Café',
            'Hôtel',
            'Résumé',
        ];

        // Assert the expected order of titles
        foreach ($entries as $key => $entry) {
            $this->assertEquals($expectedOrder[$key], $entry->title);
        }

        // Fetch entries with natural sorting applied
        $entries = $this->entryModel->getEntriesByForm($this->project->id, [
            'form_ref' => $this->formRef,
            'sort_by' => 'title',
            'sort_order' => 'DESC'
        ])->get();

        // Assert the expected order of titles
        foreach ($entries as $key => $entry) {
            $this->assertEquals(array_reverse($expectedOrder)[$key], $entry->title);
        }

        // Fetch entries with natural sorting applied
        $entries = $this->entryModel->getEntriesByForm($this->project->id, [
            'form_ref' => $this->formRef,
            'sort_by' => 'title'
        ])->get();

        // Assert the expected order of titles
        foreach ($entries as $key => $entry) {
            $this->assertEquals(array_reverse($expectedOrder)[$key], $entry->title);
        }
    }

    public function test_should_get_today_entries_for_archive()
    {
        //create fake entries from last week
        for ($i = 0; $i < 5; $i++) {
            factory(Entry::class)->create([
                'project_id' => $this->project->id,
                'form_ref' => $this->formRef,
            'created_at' => now()->subWeek()
            ]);
        }

        //create fake entry for today
        for ($i = 0; $i < 1; $i++) {
            factory(Entry::class)->create([
                'project_id' => $this->project->id,
                'form_ref' => $this->formRef,
                'created_at' => now()
            ]);
        }

        $entries = $this->entryModel->getEntriesByFormForArchive($this->project->id, [
            'form_ref' => $this->formRef,
            'filter_by' => 'created_at',
            'filter_from' => now()->startOfDay(),
            'filter_to' => now()->endOfDay(),
        ])->get();

        $this->assertEquals(1, $entries->count());
    }

    public function test_should_get_week_entries_for_archive()
    {
        //create fake entries for last month
        for ($i = 0; $i < 5; $i++) {
            factory(Entry::class)->create([
                'project_id' => $this->project->id,
                'form_ref' => $this->formRef,
                'created_at' => now()->subMonth()
            ]);
        }

        //create fake entries for this week
        for ($i = 0; $i < 5; $i++) {
            factory(Entry::class)->create([
                'project_id' => $this->project->id,
                'form_ref' => $this->formRef,
                'created_at' => now()->subWeek()
            ]);
        }

        $entries = $this->entryModel->getEntriesByFormForArchive($this->project->id, [
            'form_ref' => $this->formRef,
            'filter_by' => 'created_at',
            'filter_from' => now()->subWeek(),
            'filter_to' => now()->endOfDay(),
        ])->get();

        $this->assertEquals(5, $entries->count());
    }

    public function test_should_get_month_entries_for_archive()
    {
        //create fake entries from three months ago
        for ($i = 0; $i < 5; $i++) {
            factory(Entry::class)->create([
                'project_id' => $this->project->id,
                'form_ref' => $this->formRef,
                'created_at' => now()->subMonths(3)
            ]);
        }

        //create fake entry for today
        for ($i = 0; $i < 5; $i++) {
            factory(Entry::class)->create([
                'project_id' => $this->project->id,
                'form_ref' => $this->formRef,
                'created_at' => now()->subMonth()
            ]);
        }

        $entries = $this->entryModel->getEntriesByFormForArchive($this->project->id, [
            'form_ref' => $this->formRef,
            'filter_by' => 'created_at',
            'filter_from' => now()->subMonth(),
            'filter_to' => now()->endOfDay(),
        ])->get();

        $this->assertEquals(5, $entries->count());
    }

    public function test_should_get_year_entries_for_archive()
    {
        //create fake entries from last year (legacy json in same table)
        for ($i = 0; $i < 5; $i++) {
            factory(Entry::class)->create([
                'project_id' => $this->project->id,
                'form_ref' => $this->formRef,
                'user_id' => $this->user->id,
                'entry_data' => json_encode(['foo' => 'bar']),
                'geo_json_data' => json_encode(['lat' => 1]),
                'created_at' => now()->subYear()
            ]);
        }

        //create fake entries for this year (json in separate table)
        for ($i = 0; $i < 5; $i++) {
            $entry = factory(Entry::class)->create([
                 'project_id' => $this->project->id,
                 'form_ref' => $this->formRef,
                 'user_id' => $this->user->id,
                 'entry_data' => null,
                 'geo_json_data' => null,
                 'created_at' => now()->startOfYear()
             ]);

            factory(EntryJson::class)->create([
                'entry_id' => $entry->id,
                'project_id' => $this->project->id,
                'entry_data' => json_encode(['foo' => 'bar']),
                'geo_json_data' => json_encode(['lat' => 1])
            ]);
        }

        $entries = $this->entryModel->getEntriesByFormForArchive($this->project->id, [
            'form_ref' => $this->formRef,
            'filter_by' => 'created_at',
            'filter_from' => now()->startOfYear(),
            'filter_to' => now()->endOfYear(),
        ])->get();

        //assert only entries for this year are returned
        $this->assertEquals(5, $entries->count());
        foreach ($entries as $entry) {
            $this->assertNotNull($entry->entry_data);
            $this->assertNotNull($entry->geo_json_data);
            $this->assertJsonEquals($entry->entry_data, json_encode(['foo' => 'bar']));
            $this->assertJsonEquals($entry->geo_json_data, json_encode(['lat' => 1]));
        }

        //get all entries without filters (to check is coalesce is working)
        $entries = $this->entryModel->getEntriesByFormForArchive($this->project->id, [
            'form_ref' => $this->formRef,
        ])->get();

        $this->assertEquals(10, $entries->count());
        foreach ($entries as $entry) {
            $this->assertNotNull($entry->entry_data);
            $this->assertNotNull($entry->geo_json_data);
            $this->assertJsonEquals($entry->entry_data, json_encode(['foo' => 'bar']));
            $this->assertJsonEquals($entry->geo_json_data, json_encode(['lat' => 1]));
        }
    }

    public function test_collector_should_download_archive_with_only_own_entries()
    {
        //create fake entries
        $numOfEntries = rand(1, 5);

        //create a collector user for the project
        $collectorA = factory(User::class)->create();

        //create another collector
        $collectorB = factory(User::class)->create();

        for ($i = 0; $i < $numOfEntries; $i++) {
            //add a mix of new and legacy entries (json in same table)
            if ($i % 2 === 0) {
                $entry = factory(Entry::class)->create([
                    'project_id' => $this->project->id,
                    'user_id' => $collectorA->id,
                    'form_ref' => $this->formRef,
                    'title' => 'Ciao - ' . $i,
                    'entry_data' => null,
                    'geo_json_data' => null
                ]);
                factory(EntryJson::class)->create([
                    'entry_id' => $entry->id,
                    'project_id' => $this->project->id,
                    'entry_data' => json_encode(['foo' => 'bar']),
                    'geo_json_data' => json_encode(['lat' => 1])
                ]);
            } else {
                factory(Entry::class)->create([
                    'project_id' => $this->project->id,
                    'user_id' => $collectorA->id,
                    'form_ref' => $this->formRef,
                    'title' => 'Ciao - ' . $i,
                    'entry_data' => json_encode(['foo' => 'bar']),
                    'geo_json_data' => json_encode(['lat' => 1])
                ]);
            }
        }

        $this->assertEquals(0, $this->entryModel->getEntriesByFormForArchive(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'user_id' => $this->user->id
            ],
            []
        )->count());

        $this->assertEquals(0, $this->entryModel->getEntriesByFormForArchive(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'user_id' => $this->superadmin->id
            ],
            []
        )->count());

        $this->assertEquals($numOfEntries, $this->entryModel->getEntriesByFormForArchive(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'user_id' => $collectorA->id
            ],
            []
        )->count());

        //collectorB should not see any entries
        $this->assertEquals(0, $this->entryModel->getEntriesByFormForArchive(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'user_id' => $collectorB->id
            ],
            []
        )->count());

        //assert json data is returned
        $entries = $this->entryModel->getEntriesByFormForArchive(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'user_id' => $collectorA->id
            ],
            []
        )->get();
        foreach ($entries as $entry) {
            $this->assertNotNull($entry->entry_data);
            $this->assertNotNull($entry->geo_json_data);
            $this->assertJsonEquals($entry->entry_data, json_encode(['foo' => 'bar']));
            $this->assertJsonEquals($entry->geo_json_data, json_encode(['lat' => 1]));
        }
    }
}
