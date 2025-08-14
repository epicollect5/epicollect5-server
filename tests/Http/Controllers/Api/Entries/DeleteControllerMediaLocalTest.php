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

class DeleteControllerMediaLocalTest extends TestCase
{
    use DatabaseTransactions;

    private string $endpoint = 'api/internal/deletion/media/';

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
        $gzippedData = gzencode($jsonData);
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
            $this->overrideStorageDriver('local');

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

    public function test_it_should_delete_media_but_leave_entries_in_place()
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
            Storage::disk('entry_thumb')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
            //audio
            Storage::disk('audio')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
            //video
            Storage::disk('video')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
        }

        $this->assertCount($numOfEntries, Entry::where('project_id', $this->project->id)->get());

        //hit the delete endpoint
        $payload = [
            'data' => [
                'project-name' => $this->project->name
            ]
        ];
        $response = [];
        $mediaFolders = config('epicollect.media.entries_deletable');
        try {
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(200);
            $response[0]->assertExactJson([
                "data" => [
                    "code" => "ec5_407",
                    "title" => "Chunk media deleted successfully.",
                    "deleted" => sizeof($mediaFolders) * $numOfEntries
                ]
            ]);
            $this->assertCount($numOfEntries, Entry::where('project_id', $this->project->id)->get());

            //assert media files are deleted (a chunk is 1000 entries so we delete all media)
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

    public function test_it_should_delete_entry_original_media_chunk_and_leave_entries_in_place()
    {
        $formRef = $this->projectDefinition['data']['project']['forms'][0]['ref'];
        $chunkSize = config('epicollect.setup.bulk_deletion.chunk_size_media');

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

            //add 1100 files for testing (no need to add 20.000, chunk size is 1000)
            if ($i < ($chunkSize + 100)) {
                //photo
                Storage::disk('entry_original')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
                Storage::disk('entry_thumb')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
                //audio
                Storage::disk('audio')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
                //video
                Storage::disk('video')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');

                $mediaUuids[] = $entry->uuid;
            }
        }
        $this->assertCount($numOfEntries, Entry::where('project_id', $this->project->id)->get());
        $this->assertEquals(sizeof($mediaUuids), $chunkSize + 100);

        //hit the delete media endpoint
        $payload = [
            'data' => [
                'project-name' => $this->project->name
            ]
        ];
        $response = [];
        try {
            //delete a chunk of media
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(200);
            $response[0]->assertExactJson([
                "data" => [
                    "code" => "ec5_407",
                    "deleted" => $chunkSize,
                    "title" => "Chunk media deleted successfully."
                ]
            ]);
            $this->assertCount($numOfEntries, Entry::where('project_id', $this->project->id)->get());

            //assert media files are deleted, up to 1000
            $photos = Storage::disk('entry_original')->files($this->project->ref);
            $photosThumbs = Storage::disk('entry_thumb')->files($this->project->ref);
            $audios = Storage::disk('audio')->files($this->project->ref);
            $videos = Storage::disk('video')->files($this->project->ref);

            $totalRemaining = sizeof($photos) + sizeof($photosThumbs) +  sizeof($audios) + sizeof($videos);
            $this->assertEquals(4 * ($chunkSize  + 100) - $chunkSize, $totalRemaining, 'Total remaining media files count mismatch');
            $this->assertCount(100, $photos, 'Unexpected number of photo files remaining');
            $this->assertCount($chunkSize + 100, $photosThumbs, 'Unexpected number of photo thumbnails remaining');
            $this->assertCount($chunkSize + 100, $audios, 'Unexpected number of audio files remaining');
            $this->assertCount($chunkSize + 100, $videos, 'Unexpected number of video files remaining');

            //now remove all the leftover fake files
            Storage::disk('entry_original')->deleteDirectory($this->project->ref);
            Storage::disk('entry_thumb')->deleteDirectory($this->project->ref);
            Storage::disk('audio')->deleteDirectory($this->project->ref);
            Storage::disk('video')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_delete_all_media_and_leave_entries_in_place()
    {
        $formRef = $this->projectDefinition['data']['project']['forms'][0]['ref'];
        $chunkSize = config('epicollect.setup.bulk_deletion.chunk_size_media');

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

            //add 1000 files in total for testing (no need to add 20.000, chunk size is 1000)
            if ($i < (250)) {
                //photo
                Storage::disk('entry_original')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
                Storage::disk('entry_thumb')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.jpg', '');
                //audio
                Storage::disk('audio')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');
                //video
                Storage::disk('video')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');

                $mediaUuids[] = $entry->uuid;
            }
        }
        $this->assertCount($numOfEntries, Entry::where('project_id', $this->project->id)->get());
        $this->assertEquals(4 * sizeof($mediaUuids), $chunkSize);

        //hit the delete media endpoint
        $payload = [
            'data' => [
                'project-name' => $this->project->name
            ]
        ];
        $response = [];
        try {
            //delete a chunk of media
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(200);
            $response[0]->assertExactJson([
                "data" => [
                    "code" => "ec5_407",
                    "deleted" => $chunkSize,
                    "title" => "Chunk media deleted successfully."
                ]
            ]);
            $this->assertCount($numOfEntries, Entry::where('project_id', $this->project->id)->get());

            //assert media files are all deleted
            $photos = Storage::disk('entry_original')->files($this->project->ref);
            $photosThumbs = Storage::disk('entry_thumb')->files($this->project->ref);
            $audios = Storage::disk('audio')->files($this->project->ref);
            $videos = Storage::disk('video')->files($this->project->ref);

            $totalRemaining = sizeof($photos) + sizeof($photosThumbs) +  sizeof($audios) + sizeof($videos);
            $this->assertEquals(0, $totalRemaining, 'Total remaining media files count mismatch');
            $this->assertCount(0, $photos, 'Unexpected number of photo files remaining');
            $this->assertCount(0, $photosThumbs, 'Unexpected number of photo thumbnails remaining');
            $this->assertCount(0, $audios, 'Unexpected number of audio files remaining');
            $this->assertCount(0, $videos, 'Unexpected number of video files remaining');

            //now remove all the leftover fake files
            Storage::disk('entry_original')->deleteDirectory($this->project->ref);
            Storage::disk('entry_thumb')->deleteDirectory($this->project->ref);
            Storage::disk('audio')->deleteDirectory($this->project->ref);
            Storage::disk('video')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_delete_audio_media_chunk_and_leave_entries_in_place()
    {
        $formRef = $this->projectDefinition['data']['project']['forms'][0]['ref'];
        $chunkSize = config('epicollect.setup.bulk_deletion.chunk_size_media');

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

            //add 1100 files for testing (no need to add 20.000, chunk size is 1000)
            if ($i < ($chunkSize + 100)) {
                //audio
                Storage::disk('audio')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');

                $mediaUuids[] = $entry->uuid;
            }
        }
        $this->assertCount($numOfEntries, Entry::where('project_id', $this->project->id)->get());
        $this->assertEquals(sizeof($mediaUuids), $chunkSize + 100);

        //hit the delete media endpoint
        $payload = [
            'data' => [
                'project-name' => $this->project->name
            ]
        ];
        $response = [];
        try {
            //delete a chunk of media, audio
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(200);
            $response[0]->assertExactJson([
                "data" => [
                    "code" => "ec5_407",
                    "deleted" => $chunkSize,
                    "title" => "Chunk media deleted successfully."
                ]
            ]);
            $this->assertCount($numOfEntries, Entry::where('project_id', $this->project->id)->get());

            //assert media files are deleted, up to 1000
            $audios = Storage::disk('audio')->files($this->project->ref);

            $totalRemaining =  sizeof($audios);
            $this->assertEquals(1 * ($chunkSize  + 100) - $chunkSize, $totalRemaining, 'Total remaining media files count mismatch');
            $this->assertCount(100, $audios, 'Unexpected number of audio files remaining');

            //now remove all the leftover fake files
            Storage::disk('audio')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }

    public function test_it_should_delete_video_media_chunk_and_leave_entries_in_place()
    {
        $formRef = $this->projectDefinition['data']['project']['forms'][0]['ref'];
        $chunkSize = config('epicollect.setup.bulk_deletion.chunk_size_media');

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

            //add 1100 files for testing (no need to add 20.000, chunk size is 1000)
            if ($i < ($chunkSize + 100)) {
                //video
                Storage::disk('video')->put($this->project->ref . '/' . $entry->uuid . '_' . time() . '.mp4', '');

                $mediaUuids[] = $entry->uuid;
            }
        }
        $this->assertCount($numOfEntries, Entry::where('project_id', $this->project->id)->get());
        $this->assertEquals(sizeof($mediaUuids), $chunkSize + 100);

        //hit the delete media endpoint
        $payload = [
            'data' => [
                'project-name' => $this->project->name
            ]
        ];
        $response = [];
        try {
            //delete a chunk of media, video
            $response[] = $this->actingAs($this->user)->post($this->endpoint . $this->project->slug, $payload);
            $response[0]->assertStatus(200);
            $response[0]->assertExactJson([
                "data" => [
                    "code" => "ec5_407",
                    "deleted" => $chunkSize,
                    "title" => "Chunk media deleted successfully."
                ]
            ]);
            $this->assertCount($numOfEntries, Entry::where('project_id', $this->project->id)->get());

            //assert media files are deleted, up to 1000
            $videos = Storage::disk('video')->files($this->project->ref);

            $totalRemaining =  sizeof($videos);
            $this->assertEquals(1 * ($chunkSize  + 100) - $chunkSize, $totalRemaining, 'Total remaining media files count mismatch');
            $this->assertCount(100, $videos, 'Unexpected number of video files remaining');

            //now remove all the leftover fake files
            Storage::disk('video')->deleteDirectory($this->project->ref);
        } catch (Throwable $e) {
            $this->logTestError($e, $response);
        }
    }



    public function test_it_should_catch_deletion_process_already_running()
    {
        $formRef = $this->projectDefinition['data']['project']['forms'][0]['ref'];
        $numOfEntries = rand(2000, 5000);
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

        $this->assertCount($numOfEntries, Entry::where('project_id', $this->project->id)->get());
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
                //delete a chunk of media
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
