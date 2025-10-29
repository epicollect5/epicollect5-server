<?php

namespace Tests\Services\Media;

use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStats;
use ec5\Services\Media\PhotoSaverService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use Mockery;
use Storage;
use Illuminate\Http\Testing\File;

class PhotoSaverServiceLocalTest extends TestCase
{
    use DatabaseTransactions;
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->overrideStorageDriver('local');
    }

    /**
     * @throws Exception
     */
    public function test_service_successfully_saves_image__locally_and_updates_project_stats()
    {
        $project = factory(Project::class)->create();
        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => 0,
            'total_files' => 0,
            'photo_files' => 0,
            'photo_bytes' => 0,
            'audio_files' => 0,
            'audio_bytes' => 0,
            'video_files' => 0,
            'video_bytes' => 0,
            'total_bytes' => 0,
            'form_counts' => json_encode([]),
            'branch_counts' => json_encode([])
        ]);
        $fileName = Uuid::uuid4()->toString(). '_' . time() . '.jpg';
        $disk = 'photo';
        $fileSize = 1024 * 768 * 3; // Approx size for a 1024x768 image
        $webpFileName = pathinfo($fileName, PATHINFO_FILENAME) . '.webp';
        // // Create a fake uploaded file
        $uploadedFile = File::fake() ->image($fileName, 1024, 768) ->size($fileSize / 1024); // size() expects KB
        $encodedImage = PhotoSaverService::processImage(
            $uploadedFile->getPathname(),
            [1024, 768],
            config('epicollect.media.quality.webp')
        );
        $compressedSize = strlen($encodedImage);

        // Mock Storage facade for successful save
        Storage::shouldReceive('disk')
            ->with($disk)
            ->zeroOrMoreTimes()
            ->andReturnSelf();

        Storage::shouldReceive('exists')
            ->with($project->ref)
            ->once()
            ->andReturn(false);

        Storage::shouldReceive('makeDirectory')
            ->with($project->ref)
            ->once()
            ->andReturn(true);

        Storage::shouldReceive('put')
            ->with($project->ref . '/' . $webpFileName, Mockery::any(), Mockery::any())
            ->once()
            ->andReturn(true);

        // Assert service returns true on successful save
        $result = PhotoSaverService::saveImage($project->ref, $project->id, $uploadedFile, $fileName, $disk);
        $this->assertTrue($result);

        //assert the file size is correctly recorded in project stats
        $this->assertDatabaseHas('project_stats', [
            'project_id' => $project->id,
            'photo_bytes' => $compressedSize,
            'photo_files' => 1,
            'audio_bytes' => 0,
            'audio_files' => 0,
            'video_bytes' => 0,
            'video_files' => 0,
            'total_bytes' => $compressedSize,
            'total_files' => 1
        ]);
    }
}
