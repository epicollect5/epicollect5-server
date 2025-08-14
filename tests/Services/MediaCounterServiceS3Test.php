<?php

namespace Tests\Services;

use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use ec5\Models\User\User;
use ec5\Services\Media\MediaCounterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ramsey\Uuid\Uuid;
use Storage;
use Tests\TestCase;

class MediaCounterServiceS3Test extends TestCase
{
    use DatabaseTransactions;

    private Project $project;
    private Entry $entry;

    public function setUp(): void
    {
        parent::setUp();

        //set storage (and all disks) to s3 storage
        $this->overrideStorageDriver('s3');

        //create fake user
        $user = factory(User::class)->create();

        //create a project with that user as creator
        $this->project = factory(Project::class)->create(
            [
                'created_by' => $user->id,
                'access' => config('epicollect.strings.project_access.private')
            ]
        );
        //create a fake entry
        $this->entry = factory(Entry::class)->create(
            [
                'project_id' => $this->project->id,
                'user_id' => $user->id,
                'form_ref' => $this->project->ref . '_' . uniqid(),
                'uuid' => Uuid::uuid4()->toString()
            ]
        );
    }

    public function test_count_media_s3_no_folders()
    {
        $mediaCounterService = new MediaCounterService();
        $counters = $mediaCounterService->countersMedia($this->project->ref);
        $this->assertEquals(0, $counters['counters']['total']);
        $this->assertEquals(0, $counters['counters']['photo']);
        $this->assertEquals(0, $counters['counters']['audio']);
        $this->assertEquals(0, $counters['counters']['video']);
    }

    public function test_count_media_s3_with_photos()
    {
        //add some fake photos
        $numOfPhotos = rand(2, 10);
        for ($i = 0; $i < $numOfPhotos; $i++) {
            //photo
            sleep(1);//to void overriding the file names due to time()
            Storage::disk('entry_original')->put($this->project->ref . '/' . $this->entry->uuid . '_' . time() . '.jpg', '');
            Storage::disk('entry_thumb')->put($this->project->ref . '/' . $this->entry->uuid . '_' . time() . '.jpg', '');
        }

        $mediaCounterService = new MediaCounterService();
        $counters = $mediaCounterService->countersMedia($this->project->ref);
        $this->assertEquals($numOfPhotos * 2, $counters['counters']['total']);
        $this->assertEquals($numOfPhotos * 2, $counters['counters']['photo']);
        $this->assertEquals(0, $counters['counters']['audio']);
        $this->assertEquals(0, $counters['counters']['video']);
    }

    public function test_count_media_s3_with_audios()
    {
        //add some fake audios
        $numOfAudios = rand(2, 10);
        for ($i = 0; $i < $numOfAudios; $i++) {
            sleep(1);//to avoid overriding the file names due to time()
            Storage::disk('audio')->put($this->project->ref . '/' . $this->entry->uuid . '_' . time() . '.mp4', '');
        }

        $mediaCounterService = new MediaCounterService();
        $counters = $mediaCounterService->countersMedia($this->project->ref);
        $this->assertEquals($numOfAudios, $counters['counters']['total']);
        $this->assertEquals(0, $counters['counters']['photo']);
        $this->assertEquals($numOfAudios, $counters['counters']['audio']);
        $this->assertEquals(0, $counters['counters']['video']);
    }

    public function test_count_media_s3_with_videos()
    {
        //add some fake videos
        $numOfVideos = rand(2, 10);
        for ($i = 0; $i < $numOfVideos; $i++) {
            sleep(1);//to avoid overriding the file names due to time()
            Storage::disk('video')->put($this->project->ref . '/' . $this->entry->uuid . '_' . time() . '.mp4', '');
        }

        $mediaCounterService = new MediaCounterService();
        $counters = $mediaCounterService->countersMedia($this->project->ref);
        $this->assertEquals($numOfVideos, $counters['counters']['total']);
        $this->assertEquals(0, $counters['counters']['photo']);
        $this->assertEquals(0, $counters['counters']['audio']);
        $this->assertEquals($numOfVideos, $counters['counters']['video']);
    }

    public function test_count_media_s3_with_all_media_types()
    {
        //add some fake media
        $numOfMedia = rand(2, 10);
        for ($i = 0; $i < $numOfMedia; $i++) {
            //photo
            sleep(1);//to avoid overriding the file names due to time()
            Storage::disk('entry_original')->put($this->project->ref . '/' . $this->entry->uuid . '_' . time() . '.jpg', '');
            Storage::disk('entry_thumb')->put($this->project->ref . '/' . $this->entry->uuid . '_' . time() . '.jpg', '');
            Storage::disk('audio')->put($this->project->ref . '/' . $this->entry->uuid . '_' . time() . '.mp4', '');
            Storage::disk('video')->put($this->project->ref . '/' . $this->entry->uuid . '_' . time() . '.mp4', '');
        }

        $mediaCounterService = new MediaCounterService();
        $counters = $mediaCounterService->countersMedia($this->project->ref);
        $this->assertEquals($numOfMedia * 4, $counters['counters']['total']);
        $this->assertEquals($numOfMedia * 2, $counters['counters']['photo']);
        $this->assertEquals($numOfMedia, $counters['counters']['audio']);
        $this->assertEquals($numOfMedia, $counters['counters']['video']);
    }

    public function tearDown(): void
    {
        // Disks to clear
        $disks = [
            'entry_original',
            'entry_thumb',
            'project_thumb',
            'project_mobile_logo',
            'audio',
            'video',
            'temp',
        ];

        foreach ($disks as $disk) {
            Storage::disk($disk)->deleteDirectory($this->project->ref);
        }

        parent::tearDown();
    }
}
