<?php

namespace Tests\Services\Media;

use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStats;
use ec5\Services\Media\AudioVideoSaverService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use Storage;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class AudioVideoSaverServiceLocalTest extends TestCase
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
    public function test_service_successfully_saves_file_local()
    {
        $project = factory(Project::class)->create();
        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
        ]);

        $fileName = Uuid::uuid4()->toString(). '_' . time() . '.mp4';
        $disk = 'audio';
        $fileSizeKB = 2048; // 2MB in KB
        $fileBytes = $fileSizeKB * 1024; // 2,097,152 bytes

        // Create a fake audio mp4 file
        $file = UploadedFile::fake()
            ->create($fileName, $fileSizeKB, 'audio/mp4'); // size in KB

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

        $targetPath = $project->ref . '/' . $fileName;
        Storage::shouldReceive('put')
           ->once()
           ->withArgs(function ($path, $stream, $options) use ($targetPath) {
               // Assert the path is exactly what we expect
               if ($path !== $targetPath) {
                   return false;
               }

               // Optionally assert that $stream is a resource
               if (!is_resource($stream)) {
                   return false;
               }

               // Assert options
               return $options['visibility'] === 'public'
                   && $options['directory_visibility'] === 'public';
           })
           ->andReturn(true);

        // Assert service returns true on successful save
        $result = AudioVideoSaverService::saveFile(
            $project->ref,
            $project->id,
            $file,
            $fileName,
            $disk,
            false
        );
        $this->assertTrue($result);

        // Assert project stats updated correctly
        $projectStats = ProjectStats::where('project_id', $project->id)->first();
        $this->assertEquals(0, $projectStats->photo_bytes);
        $this->assertEquals(0, $projectStats->photo_files);
        $this->assertEquals(0, $projectStats->video_bytes);
        $this->assertEquals(0, $projectStats->video_files);
        $this->assertEquals($fileBytes, $projectStats->audio_bytes);
        $this->assertEquals(1, $projectStats->audio_files);
        $this->assertEquals($fileBytes, $projectStats->total_bytes);
        $this->assertEquals(1, $projectStats->total_files);
    }
}
