<?php

namespace Tests\Http\Controllers\Api\Entries;

use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\BranchEntryArchive;
use ec5\Models\Entries\Entry;
use ec5\Models\Entries\EntryArchive;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ramsey\Uuid\Uuid;
use Tests\Generators\EntryGenerator;
use Tests\Generators\ProjectDefinitionGenerator;
use Tests\TestCase;

//todo: need to test archiving at any level (5 forms), also branches
class ArchiveControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp()
    {
        parent::setUp();

        $user = factory(User::class)->create();
        $projectDefinition = ProjectDefinitionGenerator::createProject(5);
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'name' => array_get($projectDefinition, 'data.project.name'),
                'slug' => array_get($projectDefinition, 'data.project.slug'),
                'ref' => array_get($projectDefinition, 'data.project.ref'),
                'access' => config('epicollect.strings.project_access.private')
            ]
        );
        //add role
        factory(ProjectRole::class)->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.creator')
        ]);

        //create basic project definition
        factory(ProjectStructure::class)->create(
            [
                'project_id' => $project->id,
                'project_definition' => json_encode($projectDefinition['data'], JSON_UNESCAPED_SLASHES)
            ]
        );

        factory(ProjectStats::class)->create(
            [
                'project_id' => $project->id,
                'total_entries' => 0
            ]
        );
        $entryGenerator = new EntryGenerator($projectDefinition);

        //upload the project definition via the formbuilder controller
        // Convert data array to JSON
        $jsonData = json_encode($projectDefinition);
        // Gzip Compression
        $gzippedData = gzencode($jsonData); // '9' is the compression level (0-9, where 9 is highest)
        // Base64 Encoding
        $base64EncodedData = base64_encode($gzippedData);

        //see https://github.com/laravel/framework/issues/46455
        $response = [];
        try {
            $response[] = $this->actingAs($user)
                ->call('POST', 'api/internal/formbuilder/' . $project->slug,
                    [],
                    [],
                    [],
                    [], $base64EncodedData);

            $response[0]->assertStatus(200);
            $this->assertSame(json_decode($response[0]->getContent(), true), $projectDefinition);
            //assert there are no entries or branch entries
            $this->assertCount(0, Entry::where('project_id', $project->id)->get());
            $this->assertCount(0, BranchEntry::where('project_id', $project->id)->get());

            $this->user = $user;
            $this->project = $project;
            $this->projectDefinition = $projectDefinition;
            $this->entryGenerator = $entryGenerator;
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_detect_missing_data_key()
    {
        $formRef = $this->projectDefinition['data']['project']['forms'][0]['ref'];
        $entry = factory(Entry::class)->create(
            [
                'project_id' => $this->project->id,
                'user_id' => $this->user->id,
                'form_ref' => $formRef,
                'uuid' => Uuid::uuid4()->toString()
            ]
        );

        $formCounts = [
            $formRef => [
                'count' => 1,
                'last_entry_created' => $entry->created_at,
                'first_entry_created' => $entry->created_at
            ]
        ];
        ProjectStats::where('project_id', $this->project->id)
            ->update([
                'form_counts' => json_encode($formCounts),
                'total_entries' => 1
            ]);
        //build archive payload
        $payload = $this->createPayload(
            $this->user->id,
            $entry->uuid,
            $formRef,
            0,
            0
        );

        $this->assertCount(1, Entry::where('uuid', $entry->uuid)->get());
        $this->assertCount(0, EntryArchive::where('uuid', $entry->uuid)->get());
        $this->assertEquals(1,
            ProjectStats::where('project_id', $this->project->id)
                ->value('total_entries'));

        //hit the archive endpoint
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post('api/internal/archive/' . $this->project->slug, [$payload]);
            $response[0]->assertStatus(400);
            $response[0]->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_269",
                        "title" => "Missing 'data' key in structure",
                        "source" => "validation"
                    ]
                ]
            ]);

            $this->assertCount(1, Entry::where('uuid', $entry->uuid)->get());
            $this->assertCount(0, EntryArchive::where('uuid', $entry->uuid)->get());
            $this->assertEquals(1,
                ProjectStats::where('project_id', $this->project->id)
                    ->value('total_entries'));

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_archive_entry()
    {
        $formRef = $this->projectDefinition['data']['project']['forms'][0]['ref'];
        $entry = factory(Entry::class)->create(
            [
                'project_id' => $this->project->id,
                'user_id' => $this->user->id,
                'form_ref' => $formRef,
                'uuid' => Uuid::uuid4()->toString()
            ]
        );

        $formCounts = [
            $formRef => [
                'count' => 1,
                'last_entry_created' => $entry->created_at,
                'first_entry_created' => $entry->created_at
            ]
        ];
        ProjectStats::where('project_id', $this->project->id)
            ->update([
                'form_counts' => json_encode($formCounts),
                'total_entries' => 1
            ]);

        //todo: build archive payload
        $payload = $this->createPayload(
            $this->user->id,
            $entry->uuid,
            $formRef,
            0,
            0
        );
        $this->assertCount(1, Entry::where('uuid', $entry->uuid)->get());
        $this->assertCount(0, EntryArchive::where('uuid', $entry->uuid)->get());
        $this->assertEquals(1,
            ProjectStats::where('project_id', $this->project->id)
                ->value('total_entries'));

        //hit the archive endpoint
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post('api/internal/archive/' . $this->project->slug, ['data' => $payload]);
            $response[0]->assertStatus(200);
            $response[0]->assertExactJson([
                "data" => [
                    "code" => "ec5_236",
                    "title" => "Entry successfully archived"
                ]
            ]);
            $this->assertCount(0, Entry::where('uuid', $entry->uuid)->get());
            $this->assertCount(1, EntryArchive::where('uuid', $entry->uuid)->get());
            $this->assertEquals(0,
                ProjectStats::where('project_id', $this->project->id)
                    ->value('total_entries'));

            $formCounts = json_decode(ProjectStats::where('project_id', $this->project->id)
                ->value('form_counts'), true);
            $this->assertEquals([], $formCounts);
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_archive_child_entry()
    {
        //todo: add hierarchy entry to the project
        $formRef = $this->projectDefinition['data']['project']['forms'][0]['ref'];
        $entry = factory(Entry::class)->create(
            [
                'project_id' => $this->project->id,
                'user_id' => $this->user->id,
                'form_ref' => $formRef,
                'uuid' => Uuid::uuid4()->toString()
            ]
        );

        $childFormRef = $this->projectDefinition['data']['project']['forms'][1]['ref'];
        $childEntry = factory(Entry::class)->create(
            [
                'project_id' => $this->project->id,
                'user_id' => $this->user->id,
                'form_ref' => $childFormRef,
                'uuid' => Uuid::uuid4()->toString(),
                'parent_form_ref' => $formRef,
                'parent_uuid' => $entry->uuid

            ]
        );

        //update $entry with child count
        $entry->child_counts = 1;
        $entry->save();


        $formCounts = [
            $formRef => [
                'count' => 1,
                'last_entry_created' => $entry->created_at,
                'first_entry_created' => $entry->created_at
            ],
            $childFormRef => [
                'count' => 1,
                'last_entry_created' => $entry->created_at,
                'first_entry_created' => $entry->created_at
            ],
        ];
        ProjectStats::where('project_id', $this->project->id)
            ->update([
                'form_counts' => json_encode($formCounts),
                'total_entries' => 2
            ]);

        $this->assertCount(1, Entry::where('uuid', $entry->uuid)->get());
        $this->assertEquals(1, Entry::where('uuid', $entry->uuid)->value('child_counts'));

        $this->assertCount(0, EntryArchive::where('uuid', $entry->uuid)->get());
        $this->assertCount(1, Entry::where('uuid', $childEntry->uuid)->get());
        $this->assertCount(0, EntryArchive::where('uuid', $childEntry->uuid)->get());

        //build archive payload (child entry)
        $payload = $this->createPayload(
            $this->user->id,
            $childEntry->uuid,
            $childFormRef,
            0,
            0
        );
        //add parent metadata to payload
        $payload['relationships']['parent'] = [
            'data' => [
                'parent_form_ref' => $formRef,
                'parent_entry_uuid' => $entry->uuid
            ]
        ];

        //hit the archive endpoint
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post('api/internal/archive/' . $this->project->slug, ['data' => $payload]);
            $response[0]->assertStatus(200);
            $response[0]->assertExactJson([
                "data" => [
                    "code" => "ec5_236",
                    "title" => "Entry successfully archived"
                ]
            ]);
            $this->assertCount(1, Entry::where('uuid', $entry->uuid)->get());
            $this->assertCount(0, EntryArchive::where('uuid', $entry->uuid)->get());
            $this->assertCount(0, Entry::where('uuid', $childEntry->uuid)->get());
            $this->assertCount(1, EntryArchive::where('uuid', $childEntry->uuid)->get());

            $this->assertEquals(1,
                ProjectStats::where('project_id', $this->project->id)
                    ->value('total_entries'));

            $formCounts = json_decode(ProjectStats::where('project_id', $this->project->id)
                ->value('form_counts'), true);

            $this->assertEquals([
                $formRef => [
                    'count' => 1,
                    'last_entry_created' => $entry->created_at,
                    'first_entry_created' => $entry->created_at
                ]], $formCounts);

            //also check parent entry child count was updated
            $this->assertEquals(0, Entry::where('uuid', $entry->uuid)->value('child_counts'));
        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_archive_entry_and_child_entries()
    {
        $uuids = [];
        $forms = $this->projectDefinition['data']['project']['forms'];
        $formRef = $forms[0]['ref'];
        $entry = factory(Entry::class)->create(
            [
                'project_id' => $this->project->id,
                'user_id' => $this->user->id,
                'form_ref' => $formRef,
                'uuid' => Uuid::uuid4()->toString()
            ]
        );

        $uuids[] = $entry->uuid;

        $parentEntry = $entry; // Initial parent entry
        for ($i = 1; $i < config('epicollect.limits.formsMaxCount'); $i++) {
            $childFormRef = $forms[$i]['ref'];
            $childEntry = factory(Entry::class)->create([
                'project_id' => $this->project->id,
                'user_id' => $this->user->id,
                'form_ref' => $childFormRef,
                'uuid' => Uuid::uuid4()->toString(),
                'parent_uuid' => $parentEntry->uuid,
                'parent_form_ref' => ($i === 1) ? $formRef : $forms[$i - 1]['ref'],
                'child_counts' => $i === 4 ? 0 : 1
            ]);

            $uuids[] = $childEntry->uuid;

            $this->assertCount(1, Entry::where('uuid', $childEntry->uuid)->get());
            $this->assertCount(0, EntryArchive::where('uuid', $childEntry->uuid)->get());
            // Set the created child entry as the parent for the next iteration
            $parentEntry = $childEntry;
        }


        //update $entry with child count
        $entry->child_counts = 4;
        $entry->save();

        $this->assertEquals(4, Entry::where('uuid', $entry->uuid)->value('child_counts'));
        $this->assertCount(1, Entry::where('uuid', $entry->uuid)->get());
        $this->assertCount(0, EntryArchive::where('uuid', $entry->uuid)->get());

        //assert project has a total of 5 entries, one per form, hierarchically
        $this->assertCount(5, Entry::where('project_id', $this->project->id)->get());
        foreach ($forms as $key => $form) {
            $this->assertCount(1, Entry::where('form_ref', $form['ref'])->get());
            $this->assertCount(0, EntryArchive::where('form_ref', $form['ref'])->get());

        }

        foreach ($uuids as $key => $uuid) {
            $this->assertCount(1, Entry::where('uuid', $uuid)->get());
            $this->assertCount(0, EntryArchive::where('uuid', $uuid)->get());
            //assert the child counts skipping last form
            if ($key === 0) {
                $this->assertEquals(4, Entry::where('uuid', $uuid)->value('child_counts'));
            }
            if ($key === 1) {
                $this->assertEquals(1, Entry::where('uuid', $uuid)->value('child_counts'));
            }
            if ($key === 2) {
                $this->assertEquals(1, Entry::where('uuid', $uuid)->value('child_counts'));
            }
            if ($key === 3) {
                $this->assertEquals(1, Entry::where('uuid', $uuid)->value('child_counts'));
            }
            if ($key === 4) {
                $this->assertEquals(0, Entry::where('uuid', $uuid)->value('child_counts'));
            }
        }

        //build archive payload to delete top parent entry
        $payload = $this->createPayload(
            $this->user->id,
            $entry->uuid,
            $formRef,
            0,
            0
        );


        //hit the archive endpoint
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post('api/internal/archive/' . $this->project->slug, ['data' => $payload]);
            $response[0]->assertStatus(200);
            $response[0]->assertExactJson([
                "data" => [
                    "code" => "ec5_236",
                    "title" => "Entry successfully archived"
                ]
            ]);
            //assert the project has zero entries; they are all archived
            $this->assertCount(0, Entry::where('project_id', $this->project->id)->get());
            $this->assertCount(5, EntryArchive::where('project_id', $this->project->id)->get());

            foreach ($forms as $form) {
                $this->assertCount(0, Entry::where('form_ref', $form['ref'])->get());
                $this->assertCount(1, EntryArchive::where('form_ref', $form['ref'])->get());
            }
            foreach ($uuids as $uuid) {
                $this->assertCount(0, Entry::where('uuid', $uuid)->get());
                $this->assertCount(1, EntryArchive::where('uuid', $uuid)->get());
            }

            $this->assertEquals(0,
                ProjectStats::where('project_id', $this->project->id)
                    ->value('total_entries'));

            $formCounts = json_decode(ProjectStats::where('project_id', $this->project->id)
                ->value('form_counts'), true);
            $this->assertEquals([], $formCounts);

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_archive_entry_and_branch_entries()
    {
        $branchUuids = [];
        $forms = $this->projectDefinition['data']['project']['forms'];
        $formRef = $forms[0]['ref'];
        $entry = factory(Entry::class)->create(
            [
                'project_id' => $this->project->id,
                'user_id' => $this->user->id,
                'form_ref' => $formRef,
                'uuid' => Uuid::uuid4()->toString()
            ]
        );

        $inputs = $forms[0]['inputs'];
        $branchRef = '';
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.branch')) {
                $branchRef = $input['ref'];
                break;
            }
        }

        //add a few branch entries
        $numOfBranchEntries = rand(1, 5);
        for ($i = 0; $i < $numOfBranchEntries; $i++) {
            $branchEntry = factory(BranchEntry::class)->create(
                [
                    'project_id' => $this->project->id,
                    'user_id' => $this->user->id,
                    'form_ref' => $formRef,
                    'owner_entry_id' => $entry->id,
                    'owner_uuid' => $entry->uuid,
                    'owner_input_ref' => $branchRef,
                    'uuid' => Uuid::uuid4()->toString()
                ]
            );
            $branchUuids[] = $branchEntry->uuid;
        }

        $branchCounts = [
            $branchRef => $numOfBranchEntries
        ];
        $entry->branch_counts = json_encode($branchCounts);
        $entry->save();

        $this->assertCount(1, Entry::where('project_id', $this->project->id)->get());
        $this->assertCount(0, EntryArchive::where('project_id', $this->project->id)->get());
        $this->assertCount($numOfBranchEntries, BranchEntry::where('project_id', $this->project->id)->get());
        $this->assertCount(0, BranchEntryArchive::where('project_id', $this->project->id)->get());


        //build archive payload
        $payload = $this->createPayload(
            $this->user->id,
            $entry->uuid,
            $formRef,
            0,
            0
        );


        //hit the archive endpoint
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post('api/internal/archive/' . $this->project->slug, ['data' => $payload]);
            $response[0]->assertStatus(200);
            $response[0]->assertExactJson([
                "data" => [
                    "code" => "ec5_236",
                    "title" => "Entry successfully archived"
                ]
            ]);

            $this->assertCount(0, Entry::where('project_id', $this->project->id)->get());
            $this->assertCount(1, EntryArchive::where('project_id', $this->project->id)->get());
            $this->assertCount(0, BranchEntry::where('project_id', $this->project->id)->get());
            $this->assertCount($numOfBranchEntries, BranchEntryArchive::where('project_id', $this->project->id)->get());

            foreach ($branchUuids as $branchUuid) {
                $this->assertCount(0, BranchEntry::where('uuid', $branchUuid)->get());
                $this->assertCount(1, BranchEntryArchive::where('uuid', $branchUuid)->get());
            }

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_should_archive_branch_entry()
    {
        $forms = $this->projectDefinition['data']['project']['forms'];
        $formRef = $forms[0]['ref'];
        $entry = factory(Entry::class)->create(
            [
                'project_id' => $this->project->id,
                'user_id' => $this->user->id,
                'form_ref' => $formRef,
                'uuid' => Uuid::uuid4()->toString()
            ]
        );

        //add branch refs
        $branchRefs = [];
        foreach ($forms as $form) {
            $inputs = $form['inputs'];
            foreach ($inputs as $input) {
                if ($input['type'] === config('epicollect.strings.branch')) {
                    $branchRefs[] = $input['ref'];
                }
            }
        }

        $branchCounts = [];
        $branchEntries = [];
        foreach ($branchRefs as $branchRef) {
            $branchEntries[] = factory(BranchEntry::class)->create(
                [
                    'project_id' => $this->project->id,
                    'user_id' => $this->user->id,
                    'form_ref' => $formRef,
                    'owner_entry_id' => $entry->id,
                    'owner_uuid' => $entry->uuid,
                    'owner_input_ref' => $branchRef,
                    'uuid' => Uuid::uuid4()->toString()
                ]
            );

            $branchCounts[$branchRef] = 1;
        }
        $entry->branch_counts = json_encode($branchCounts);
        $entry->save();

        $this->assertCount(1, Entry::where('project_id', $this->project->id)->get());
        $this->assertCount(0, EntryArchive::where('project_id', $this->project->id)->get());
        $this->assertCount(5, BranchEntry::where('project_id', $this->project->id)->get());
        $this->assertCount(0, BranchEntryArchive::where('project_id', $this->project->id)->get());

        //build archive payload
        $payload = $this->createPayload(
            $this->user->id,
            $branchEntries[0]->uuid,
            $formRef,
            0,
            0
        );

        //add branch metadata
        $payload['relationships']['branch'] = [
            'data' => [
                'owner_input_ref' => $branchRefs[0],
                'owner_entry_uuid' => $entry->uuid
            ]
        ];

        //hit the archive endpoint
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post('api/internal/archive/' . $this->project->slug, ['data' => $payload]);
            $response[0]->assertStatus(200);
            $response[0]->assertExactJson([
                "data" => [
                    "code" => "ec5_236",
                    "title" => "Entry successfully archived"
                ]
            ]);

            $this->assertCount(1, Entry::where('project_id', $this->project->id)->get());
            $this->assertCount(0, EntryArchive::where('project_id', $this->project->id)->get());
            $this->assertCount(4, BranchEntry::where('project_id', $this->project->id)->get());
            $this->assertCount(1, BranchEntryArchive::where('project_id', $this->project->id)->get());

            $branchCountsResult = Entry::where('project_id', $this->project->id)->value('branch_counts');
            $branchCounts[$branchRefs[0]] = 0;
            $this->assertEquals($branchCounts, json_decode($branchCountsResult, true));

        } catch (Exception $e) {
            $this->logTestError($e, $response);
        }
    }

    private function createPayload($userId, $entryUuid, $formRef, $branchCounts, $childCounts)
    {
        return [
            'type' => 'archive',
            'id' => $entryUuid,
            'attributes' => [
                'form' => [
                    'ref' => $formRef,
                    'type' => 'hierarchy'
                ],
                'branch_counts' => $branchCounts,
                'child_counts' => $childCounts,
            ],
            'relationships' => [
                'branch' => [],
                'parent' => [],
                'user' => [
                    'data' => [
                        'id' => $userId
                    ]
                ]
            ],
            'archive' => [
                'entry_uuid' => $entryUuid
            ]
        ];
    }
}