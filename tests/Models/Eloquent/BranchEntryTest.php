<?php

namespace Tests\Models\Eloquent;

use Carbon\Carbon;
use ec5\Libraries\Utilities\Generators;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BranchEntryTest extends TestCase
{
    use DatabaseTransactions;

    protected $faker;
    protected $user;
    protected $project;
    protected $entry;
    protected $formRef;
    protected $branchInputRef;
    protected $superadmin;

    public function setUp()
    {
        parent::setUp();

        $user = factory(User::class)->create();
        $superadmin = User::where('email', config('epicollect.setup.super_admin_user.email'))->first();
        $project = factory(Project::class)->create([
            'created_by' => $user->id
        ]);
        $formRef = Generators::formRef($project->ref);
        $branchInputRef = Generators::inputRef($formRef);

        $entry = factory(Entry::class)->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'form_ref' => $formRef,
            'title' => 'Ciao'
        ]);

        $branchEntry = factory(BranchEntry::class)->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'form_ref' => $formRef,
            'owner_input_ref' => $branchInputRef,
            'owner_entry_id' => $entry->id,
            'owner_uuid' => $entry->uuid,
            'title' => 'Ciao'
        ]);

        $this->assertEquals(1, BranchEntry::getBranchEntriesByBranchRef(
            $project->id,
            [
                'form_ref' => $formRef,
                'branch_ref' => $branchInputRef
            ],
            []
        )->count());

        $this->assertEquals(1, BranchEntry::getBranchEntriesByBranchRef(
            $project->id,
            [
                'form_ref' => $formRef,
                'branch_ref' => $branchInputRef,
                'title' => 'Ciao'
            ],
            []
        )->count());

        $this->assertEquals(0, BranchEntry::getBranchEntriesByBranchRef(
            $project->id,
            [
                'form_ref' => $formRef,
                'branch_ref' => $branchInputRef,
                'user_id' => $superadmin->id
            ],
            []
        )->count());

        $this->project = $project;
        $this->user = $user;
        $this->superadmin = $superadmin;
        $this->formRef = $formRef;
        $this->entry = $entry;
        $this->branchInputRef = $branchInputRef;


        //remove safety check entry
        $branchEntry->delete();

    }

    public function test_should_get_branch_entries_by_form_and_branch_input_ref()
    {
        //create fake branch entries
        $numOfEntries = rand(25, 50);

        for ($i = 0; $i < $numOfEntries; $i++) {
            factory(BranchEntry::class)->create([
                'project_id' => $this->project->id,
                'user_id' => $this->user->id,
                'form_ref' => $this->formRef,
                'owner_input_ref' => $this->branchInputRef,
                'owner_entry_id' => $this->entry->id,
                'owner_uuid' => $this->entry->uuid,
            ]);
        }

        $this->assertEquals($numOfEntries, BranchEntry::getBranchEntriesByBranchRef(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'branch_ref' => $this->branchInputRef
            ],
            []
        )->count());
    }

    public function test_should_get_branch_entries_by_form_and_title()
    {
        //create fake entries
        $numOfEntries = rand(25, 50);

        for ($i = 0; $i < $numOfEntries; $i++) {
            factory(BranchEntry::class)->create([
                'project_id' => $this->project->id,
                'user_id' => $this->user->id,
                'form_ref' => $this->formRef,
                'owner_input_ref' => $this->branchInputRef,
                'owner_entry_id' => $this->entry->id,
                'owner_uuid' => $this->entry->uuid,
                'title' => 'Ciao - ' . $i
            ]);
        }

        $this->assertEquals(1, BranchEntry::getBranchEntriesByBranchRef(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'branch_ref' => $this->branchInputRef,
                'title' => 'Ciao - 0'
            ],
            []
        )->count());

        $this->assertEquals($numOfEntries, BranchEntry::getBranchEntriesByBranchRef(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'branch_ref' => $this->branchInputRef,
                'title' => 'Ciao'
            ],
            []
        )->count());

        $this->assertEquals(0, BranchEntry::getBranchEntriesByBranchRef(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'branch_ref' => $this->branchInputRef,
                'title' => 'Nothing'
            ],
            []
        )->count());
    }

    public function test_should_get_branch_entries_by_user()
    {
        //create fake entries
        $numOfEntries = rand(25, 50);

        //create a collector user for the project
        $collector = factory(User::class)->create();

        for ($i = 0; $i < $numOfEntries; $i++) {
            factory(BranchEntry::class)->create([
                'project_id' => $this->project->id,
                'user_id' => $collector->id,
                'form_ref' => $this->formRef,
                'owner_input_ref' => $this->branchInputRef,
                'owner_entry_id' => $this->entry->id,
                'owner_uuid' => $this->entry->uuid,
                'title' => 'Ciao - ' . $i,
            ]);
        }

        $this->assertEquals(0, BranchEntry::getBranchEntriesByBranchRef(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'branch_ref' => $this->branchInputRef,
                'user_id' => $this->user->id
            ],
            []
        )->count());

        $this->assertEquals(0, BranchEntry::getBranchEntriesByBranchRef(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'branch_ref' => $this->branchInputRef,
                'user_id' => $this->superadmin->id
            ],
            []
        )->count());

        $this->assertEquals($numOfEntries, BranchEntry::getBranchEntriesByBranchRef(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'branch_ref' => $this->branchInputRef,
                'user_id' => $collector->id
            ],
            []
        )->count());
    }

    public function test_should_filter_entries_by_created_at()
    {
        //create fake entries
        $numOfEntries = 8;
        //create a collector user for the project
        $collector = factory(User::class)->create();

        $year = 2016;
        for ($i = 0; $i < $numOfEntries; $i++) {
            factory(BranchEntry::class)->create([
                'project_id' => $this->project->id,
                'user_id' => $collector->id,
                'form_ref' => $this->formRef,
                'owner_input_ref' => $this->branchInputRef,
                'owner_entry_id' => $this->entry->id,
                'owner_uuid' => $this->entry->uuid,
                'title' => 'Ciao - ' . $i,
                'created_at' => Carbon::create($year)->toIso8601String()
            ]);
            $year++;
        }

        $this->assertEquals($numOfEntries, BranchEntry::getBranchEntriesByBranchRef(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'branch_ref' => $this->branchInputRef,
                'user_id' => $collector->id
            ],
            []
        )->count());

        //filter by year (one by one)
        $year = 2016;
        for ($i = 0; $i < $numOfEntries; $i++) {
            $this->assertEquals(1, BranchEntry::getBranchEntriesByBranchRef(
                $this->project->id,
                [
                    'form_ref' => $this->formRef,
                    'branch_ref' => $this->branchInputRef,
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
        $this->assertEquals(3, BranchEntry::getBranchEntriesByBranchRef(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'branch_ref' => $this->branchInputRef,
                'filter_by' => 'created_at',
                'filter_from' => Carbon::create($yearFrom)->toIso8601String(),
                'filter_to' => Carbon::create($yearTo)->toIso8601String(),
            ],
            []
        )->count());

        //remove entries so far
        BranchEntry::where('project_id', $this->project->id)->delete();
        $this->assertEquals(0, BranchEntry::getBranchEntriesByBranchRef(
            $this->project->id,
            [
                'form_ref' => $this->formRef,
                'branch_ref' => $this->branchInputRef,
            ],
            []
        )->count());

        //create one entry per month
        $currentYear = Carbon::now()->year;
        for ($month = 1; $month <= 12; $month++) {
            // Create a Carbon instance for the current month and year
            $createdAt = Carbon::create($currentYear, $month)->toIso8601String();
            // Create an entry for this month
            factory(BranchEntry::class)->create([
                'project_id' => $this->project->id,
                'user_id' => $collector->id,
                'form_ref' => $this->formRef,
                'owner_input_ref' => $this->branchInputRef,
                'owner_entry_id' => $this->entry->id,
                'owner_uuid' => $this->entry->uuid,
                'title' => 'Ciao - ' . $month, // You might want to include the month in the title
                'created_at' => $createdAt
            ]);
        }

        //assert one entry per month
        for ($month = 1; $month <= 12; $month++) {
            $this->assertEquals(1, BranchEntry::getBranchEntriesByBranchRef(
                $this->project->id,
                [
                    'form_ref' => $this->formRef,
                    'branch_ref' => $this->branchInputRef,
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
            factory(BranchEntry::class)->create([
                'project_id' => $this->project->id,
                'user_id' => $collector->id,
                'form_ref' => $this->formRef,
                'owner_input_ref' => $this->branchInputRef,
                'owner_entry_id' => $this->entry->id,
                'owner_uuid' => $this->entry->uuid,
                'title' => 'Ciao - ' . $i
            ]);
        }

        // Fetch entries with natural sorting applied ASC
        $entries = BranchEntry::getBranchEntriesByBranchRef($this->project->id, [
            'form_ref' => $this->formRef,
            'branch_ref' => $this->branchInputRef,
            'sort_by' => 'title',
            'sort_order' => 'ASC'
        ])->get();

        // Assertions to test the natural sort order
        for ($i = 0; $i < $numOfEntries; $i++) {
            $this->assertEquals('Ciao - ' . $i, $entries[$i]->title);
        }

        // Fetch entries with natural sorting applied ASC
        $entries = BranchEntry::getBranchEntriesByBranchRef($this->project->id, [
            'form_ref' => $this->formRef,
            'branch_ref' => $this->branchInputRef,
            'sort_by' => 'title',
            //   'sort_order' => 'DESC' //default
        ])->get();

        // Assert in reverse order for DESC natural sorting
        for ($i = 0; $i < $numOfEntries; $i++) {
            $this->assertEquals('Ciao - ' . ($numOfEntries - 1 - $i), $entries[$i]->title);
        }

        // Fetch entries with natural sorting applied ASC
        $entries = BranchEntry::getBranchEntriesByBranchRef($this->project->id, [
            'form_ref' => $this->formRef,
            'branch_ref' => $this->branchInputRef,
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
            factory(BranchEntry::class)->create([
                'project_id' => $this->project->id,
                'user_id' => $this->user->id,
                'form_ref' => $this->formRef,
                'owner_input_ref' => $this->branchInputRef,
                'owner_entry_id' => $this->entry->id,
                'owner_uuid' => $this->entry->uuid,
                'title' => $title
            ]);
        }

        // Fetch entries with natural sorting applied
        $entries = BranchEntry::getBranchEntriesByBranchRef($this->project->id, [
            'form_ref' => $this->formRef,
            'branch_ref' => $this->branchInputRef,
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
        $entries = BranchEntry::getBranchEntriesByBranchRef($this->project->id, [
            'form_ref' => $this->formRef,
            'branch_ref' => $this->branchInputRef,
            'sort_by' => 'title',
            'sort_order' => 'DESC'
        ])->get();

        // Assert the expected order of titles
        foreach ($entries as $key => $entry) {
            $this->assertEquals(array_reverse($expectedOrder)[$key], $entry->title);
        }
    }
}