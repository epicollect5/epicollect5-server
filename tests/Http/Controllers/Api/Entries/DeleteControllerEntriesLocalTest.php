<?php

namespace Tests\Http\Controllers\Api\Entries;

use Cache;
use ec5\Libraries\Generators\EntryGenerator;
use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use Throwable;

class DeleteControllerEntriesLocalTest extends TestCase
{
    use DatabaseTransactions;

    private string $endpoint = 'api/internal/deletion/entries/';

    public function setUp(): void
    {
        parent::setUp();

        $this->clearDatabase([]);

        $user = factory(User::class)->create();
        $projectDefinition = ProjectDefinitionGenerator::createProject(5);
        $project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'name' => array_get($projectDefinition, 'data.project.name'),
                'slug' => array_get($projectDefinition, 'data.project.slug'),
                'ref' => array_get($projectDefinition, 'data.project.ref'),
                'access' => config('epicollect.strings.project_access.private'),
                //imp: set as locked to delete entries in bulk
                'status' => config('epicollect.strings.project_status.locked')
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
                ->call(
                    'POST',
                    'api/internal/formbuilder/' . $project->slug,
                    [],
                    [],
                    [],
                    [],
                    $base64EncodedData
                );

            $response[0]->assertStatus(200);
            $this->assertSame(json_decode($response[0]->getContent(), true), $projectDefinition);
            //assert there are no entries or branch entries
            $this->assertCount(0, Entry::where('project_id', $project->id)->get());
            $this->assertCount(0, BranchEntry::where('project_id', $project->id)->get());

            $this->user = $user;
            $this->project = $project;
            $this->projectDefinition = $projectDefinition;
            $this->entryGenerator = $entryGenerator;

            //set storage (and all disks) to local storage
            config([
                'filesystems.default' => 'local',
                'filesystems.disks.temp.driver' => 'local',
                'filesystems.disks.temp.root' => storage_path('app/temp'),
                'filesystems.disks.entry_original.driver' => 'local',
                'filesystems.disks.entry_original.root' => storage_path('app/entries/photo/entry_original'),
                'filesystems.disks.entry_thumb.driver' => 'local',
                'filesystems.disks.entry_thumb.root' => storage_path('app/entries/photo/entry_thumb'),
                'filesystems.disks.project_thumb.driver' => 'local',
                'filesystems.disks.project_thumb.root' => storage_path('app/projects/project_thumb'),
                'filesystems.disks.project_mobile_logo.driver' => 'local',
                'filesystems.disks.project_mobile_logo.root' => storage_path('app/projects/project_mobile_logo'),
                'filesystems.disks.audio.driver' => 'local',
                'filesystems.disks.audio.root' => storage_path('app/entries/audio'),
                'filesystems.disks.video.driver' => 'local',
                'filesystems.disks.video.root' => storage_path('app/entries/video')
            ]);


        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_catch_wrong_project_name()
    {
        //hit the delete endpoint
        $payload = [
            'data' => [
                'project-name' => 'wrong'
            ]
        ];
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400);
            $response[0]->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_399",
                        "title" => "Invalid project name",
                        "source" => "deletion-entries"
                    ]
                ]
            ]);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }

    }

    public function test_it_should_catch_status_not_locked()
    {
        $this->project->status = config('epicollect.strings.project_status.active');
        $this->project->save();
        //hit the delete endpoint
        $payload = [
            'data' => [
                'project-name' => $this->project->name
            ]
        ];
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(400);
            $response[0]->assertExactJson([
                "errors" => [
                    [
                        "code" => "ec5_91",
                        "title" => "Sorry, you cannot perform this operation.",
                        "source" => "deletion-entries"
                    ]
                ]
            ]);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_delete_all_entries_and_media()
    {
        $formRef = $this->projectDefinition['data']['project']['forms'][0]['ref'];

        $numOfEntries = rand(2, 10);
        for ($i = 0; $i < $numOfEntries; $i++) {
            $entry = factory(Entry::class)->create(
                [
                    'project_id' => $this->project->id,
                    'user_id' => $this->user->id,
                    'form_ref' => $formRef,
                    'uuid' => Uuid::uuid4()->toString()
                ]
            );
            //add a fake file per each entry (per each media type)
            //photo
            Storage::disk('entry_original')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
            //audio
            Storage::disk('audio')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
            //video
            Storage::disk('video')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
        }

        $formCounts = [
            $formRef => [
                'count' => $numOfEntries,
                'last_entry_created' => $entry->created_at,
                'first_entry_created' => $entry->created_at
            ]
        ];
        ProjectStats::where('project_id', $this->project->id)
            ->update([
                'form_counts' => json_encode($formCounts),
                'total_entries' => $numOfEntries
            ]);


        $this->assertCount($numOfEntries, Entry::where('project_id', $this->project->id)->get());
        $this->assertEquals(
            $numOfEntries,
            ProjectStats::where('project_id', $this->project->id)
                ->value('total_entries')
        );

        $totalNumberOfEntriesBefore = Entry::count();

        //hit the delete endpoint
        $payload = [
            'data' => [
                'project-name' => $this->project->name
            ]
        ];
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(200);
            $response[0]->assertExactJson([
                "data" => [
                    "code" => "ec5_400",
                    "title" => "Chunk entries deleted successfully."
                ]
            ]);
            $this->assertCount(0, Entry::where('project_id', $this->project->id)->get());
            $this->assertEquals(
                0,
                ProjectStats::where('project_id', $this->project->id)
                    ->value('total_entries')
            );

            //assert we only deleted the entries for the selected project
            $this->assertEquals($totalNumberOfEntriesBefore - $numOfEntries, Entry::count());

            $formCounts = json_decode(ProjectStats::where('project_id', $this->project->id)
                ->value('form_counts'), true);
            $this->assertEquals([], $formCounts);

            //assert media files are deleted
            $photos = Storage::disk('entry_original')->files($this->project->ref);
            $this->assertCount(0, $photos);

            $audios = Storage::disk('audio')->files($this->project->ref);
            $this->assertCount(0, $audios);

            $videos = Storage::disk('video')->files($this->project->ref);
            $this->assertCount(0, $videos);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_delete_entries_chunk_and_leave_media()
    {
        $formRef = $this->projectDefinition['data']['project']['forms'][0]['ref'];
        $chunkSize = config('epicollect.setup.bulk_deletion.chunk_size');

        $numOfEntries = rand(20000, 50000);
        $mediaUuids = [];
        for ($i = 0; $i < $numOfEntries; $i++) {
            $entry = factory(Entry::class)->create(
                [
                    'project_id' => $this->project->id,
                    'user_id' => $this->user->id,
                    'form_ref' => $formRef,
                    'uuid' => Uuid::uuid4()->toString()
                ]
            );

            //add 10 files for testing (no need to add 20.000)
            if ($i < 10) {
                //photo
                Storage::disk('entry_original')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
                //audio
                Storage::disk('audio')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
                //video
                Storage::disk('video')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');

                $mediaUuids[] = $entry->uuid;
            }
        }

        $formCounts = [
            $formRef => [
                'count' => $numOfEntries,
                'last_entry_created' => $entry->created_at,
                'first_entry_created' => $entry->created_at
            ]
        ];
        ProjectStats::where('project_id', $this->project->id)
            ->update([
                'form_counts' => json_encode($formCounts),
                'total_entries' => $numOfEntries
            ]);


        $this->assertCount($numOfEntries, Entry::where('project_id', $this->project->id)->get());
        $this->assertEquals(
            $numOfEntries,
            ProjectStats::where('project_id', $this->project->id)
                ->value('total_entries')
        );

        //hit the delete endpoint
        $payload = [
            'data' => [
                'project-name' => $this->project->name
            ]
        ];
        $response = [];
        try {
            //delete a chunk of entries
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(200);
            $response[0]->assertExactJson([
                "data" => [
                    "code" => "ec5_400",
                    "title" => "Chunk entries deleted successfully."
                ]
            ]);
            $this->assertCount($numOfEntries - $chunkSize, Entry::where('project_id', $this->project->id)->get());
            $this->assertEquals(
                $numOfEntries - $chunkSize,
                ProjectStats::where('project_id', $this->project->id)
                    ->value('total_entries')
            );

            $formCounts = json_decode(ProjectStats::where('project_id', $this->project->id)
                ->value('form_counts'), true);
            $this->assertEquals($numOfEntries - $chunkSize, $formCounts[$formRef]['count']);

            //assert media files are not touched
            $photos = Storage::disk('entry_original')->files($this->project->ref);
            $this->assertCount(sizeof($mediaUuids), $photos);
            $audios = Storage::disk('audio')->files($this->project->ref);
            $this->assertCount(sizeof($mediaUuids), $audios);
            $videos = Storage::disk('video')->files($this->project->ref);
            $this->assertCount(sizeof($mediaUuids), $videos);

            //now remove all the leftover fake files
            Storage::disk('entry_original')->deleteDirectory($this->project->ref);
            Storage::disk('audio')->deleteDirectory($this->project->ref);
            Storage::disk('video')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_delete_entries_and_child_entries_and_media()
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

        //add a fake file per each entry (per each media type)
        //photo
        Storage::disk('entry_original')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
        //audio
        Storage::disk('audio')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
        //video
        Storage::disk('video')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');


        $childFormRef = $this->projectDefinition['data']['project']['forms'][1]['ref'];
        $numOfEntries = rand(2, 10);
        for ($i = 0; $i < $numOfEntries; $i++) {
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
            //add a fake file per each entry (per each media type)
            //photo
            Storage::disk('entry_original')->put($this->project->ref . '/' . $childEntry->uuid . '_' . time() . '.jpg', '');
            //audio
            Storage::disk('audio')->put($this->project->ref . '/' . $childEntry->uuid . '_' . time() . '.mp4', '');
            //video
            Storage::disk('video')->put($this->project->ref . '/' . $childEntry->uuid . '_' . time() . '.mp4', '');
        }


        //update $entry with child count
        $entry->child_counts = $numOfEntries;
        $entry->save();

        $formCounts = [
            $formRef => [
                'count' => 1,
                'last_entry_created' => $entry->created_at,
                'first_entry_created' => $entry->created_at
            ],
            $childFormRef => [
                'count' => $numOfEntries,
                'last_entry_created' => $entry->created_at,
                'first_entry_created' => $entry->created_at
            ],
        ];
        ProjectStats::where('project_id', $this->project->id)
            ->update([
                'form_counts' => json_encode($formCounts),
                'total_entries' => 1 + $numOfEntries
            ]);

        $this->assertCount(1, Entry::where('uuid', $entry->uuid)->get());
        $this->assertEquals(1 + $numOfEntries, Entry::where('project_id', $this->project->id)->count());

        //hit the delete entries endpoint
        $payload = [
            'data' => [
                'project-name' => $this->project->name
            ]
        ];
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(200);
            $response[0]->assertExactJson([
                "data" => [
                    "code" => "ec5_400",
                    "title" => "Chunk entries deleted successfully."
                ]
            ]);
            $this->assertCount(0, Entry::where('project_id', $this->project->id)->get());
            $this->assertEquals(
                0,
                ProjectStats::where('project_id', $this->project->id)
                    ->value('total_entries')
            );

            $formCounts = json_decode(ProjectStats::where('project_id', $this->project->id)
                ->value('form_counts'), true);

            $this->assertEquals([], $formCounts);

            //also check parent entry child count was updated
            $this->assertEquals(0, Entry::where('uuid', $entry->uuid)->value('child_counts'));

            //assert media files are deleted
            $photos = Storage::disk('entry_original')->files($this->project->ref);
            $this->assertCount(0, $photos);
            $audios = Storage::disk('audio')->files($this->project->ref);
            $this->assertCount(0, $audios);
            $videos = Storage::disk('video')->files($this->project->ref);
            $this->assertCount(0, $videos);

        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_delete_entry_and_branch_entries_and_media()
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

        //add a fake file per each entry (per each media type)
        //photo
        Storage::disk('entry_original')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
        //audio
        Storage::disk('audio')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
        //video
        Storage::disk('video')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');

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

            //add a fake file per each entry (per each media type)
            //photo
            Storage::disk('entry_original')->put($this->project->ref . '/' . $branchEntry->uuid . '_' . time() . '.jpg', '');
            //audio
            Storage::disk('audio')->put($this->project->ref . '/' . $branchEntry->uuid . '_' . time() . '.mp4', '');
            //video
            Storage::disk('video')->put($this->project->ref . '/' . $branchEntry->uuid . '_' . time() . '.mp4', '');
        }

        $branchCounts = [
            $branchRef => $numOfBranchEntries
        ];
        $entry->branch_counts = json_encode($branchCounts);
        $entry->save();

        $this->assertCount(1, Entry::where('project_id', $this->project->id)->get());
        $this->assertCount($numOfBranchEntries, BranchEntry::where('project_id', $this->project->id)->get());

        //hit the delete entries endpoint
        $payload = [
            'data' => [
                'project-name' => $this->project->name
            ]
        ];
        $response = [];
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(200);
            $response[0]->assertExactJson([
                "data" => [
                    "code" => "ec5_400",
                    "title" => "Chunk entries deleted successfully."
                ]
            ]);

            $this->assertCount(0, Entry::where('project_id', $this->project->id)->get());
            $this->assertCount(0, BranchEntry::where('project_id', $this->project->id)->get());
            foreach ($branchUuids as $branchUuid) {
                $this->assertCount(0, BranchEntry::where('uuid', $branchUuid)->get());
            }

            //assert media files are deleted
            $photos = Storage::disk('entry_original')->files($this->project->ref);
            $this->assertCount(0, $photos);
            $audios = Storage::disk('audio')->files($this->project->ref);
            $this->assertCount(0, $audios);
            $videos = Storage::disk('video')->files($this->project->ref);
            $this->assertCount(0, $videos);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_catch_deletion_process_already_running()
    {
        $formRef = $this->projectDefinition['data']['project']['forms'][0]['ref'];
        $numOfEntries = rand(2000, 5000);
        $mediaUuids = [];
        $entry = [];
        for ($i = 0; $i < $numOfEntries; $i++) {
            $entry = factory(Entry::class)->create(
                [
                    'project_id' => $this->project->id,
                    'user_id' => $this->user->id,
                    'form_ref' => $formRef,
                    'uuid' => Uuid::uuid4()->toString()
                ]
            );

            //add 10 files for testing (no need to add 20.000)
            if ($i < 10) {
                //photo
                Storage::disk('entry_original')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
                //audio
                Storage::disk('audio')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
                //video
                Storage::disk('video')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');

                $mediaUuids[] = $entry->uuid;
            }
        }

        $formCounts = [
            $formRef => [
                'count' => $numOfEntries,
                'last_entry_created' => $entry->created_at,
                'first_entry_created' => $entry->created_at
            ]
        ];
        ProjectStats::where('project_id', $this->project->id)
            ->update([
                'form_counts' => json_encode($formCounts),
                'total_entries' => $numOfEntries
            ]);


        $this->assertCount($numOfEntries, Entry::where('project_id', $this->project->id)->get());
        $this->assertEquals(
            $numOfEntries,
            ProjectStats::where('project_id', $this->project->id)
                ->value('total_entries')
        );

        //hit the delete endpoint
        $payload = [
            'data' => [
                'project-name' => $this->project->name
            ]
        ];
        $response = [];
        //set user in cache for the deletion process
        $userId = $this->user->id;
        $userCacheKey = 'bulk_entries_deletion_user_' . $userId;
        // Acquire lock
        $lock = Cache::lock($userCacheKey, 600);
        if ($lock->get()) {
            try {
                //delete a chunk of entries
                $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
                $response[0]->assertStatus(400);

                $response[0]->assertExactJson([
                    "errors" => [[
                        "code" => "ec5_255",
                        "title" => "Too many requests have been made. Please try again later.",
                        "source" => "errors"
                    ]]
                ]);
                $this->assertCount($numOfEntries, Entry::where('project_id', $this->project->id)->get());
                $this->assertEquals(
                    $numOfEntries,
                    ProjectStats::where('project_id', $this->project->id)
                        ->value('total_entries')
                );

                $formCounts = json_decode(ProjectStats::where('project_id', $this->project->id)
                    ->value('form_counts'), true);
                $this->assertEquals($numOfEntries, $formCounts[$formRef]['count']);

                //assert media files are not touched
                $photos = Storage::disk('entry_original')->files($this->project->ref);
                $this->assertCount(sizeof($mediaUuids), $photos);
                $audios = Storage::disk('audio')->files($this->project->ref);
                $this->assertCount(sizeof($mediaUuids), $audios);
                $videos = Storage::disk('video')->files($this->project->ref);
                $this->assertCount(sizeof($mediaUuids), $videos);

            } catch (Throwable $e) {
                $this->logTestError($e, $response);
            } finally {
                //now remove all the leftover fake files
                Storage::disk('entry_original')->deleteDirectory($this->project->ref);
                Storage::disk('audio')->deleteDirectory($this->project->ref);
                Storage::disk('video')->deleteDirectory($this->project->ref);
                $lock->release();
            }
        } else {
            $this->fail('Failed to acquire the lock');
        }
    }
}
