<?php

namespace Tests\Traits\Eloquent;

use ec5\Http\Controllers\Api\Entries\DeleteController;
use ec5\Libraries\Utilities\Generators;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ramsey\Uuid\Uuid;
use Storage;
use Tests\TestCase;
use Mockery;

class RemoverLocalTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();



        $this->overrideStorageDriver('local');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }


    /**
     * @throws Exception
     */
    public function test_remove_media_chunk_deletes_files_from_local_storage()
    {
        Storage::fake('photo');
        $projectRef = Generators::projectRef();
        $uuid = Uuid::uuid4()->toString();

        // Arrange: create a test projectRef with some files
        $numOfFiles = rand(2, 10);
        $files = [];
        for ($i = 0; $i < $numOfFiles; $i++) {
            sleep(1);
            $files[] = $projectRef.'/'.$uuid.'_'.time().'.jpg';
            Storage::disk('photo')->put($files[$i], 'content' . $i);
        }

        // Override config so only our fake local disk is used
        config()->set('epicollect.media.entries_deletable', ['photo']);
        config()->set('filesystems.disks.photo', [
            'driver' => 'local',
            'root'   => storage_path('app/entries/photo/entry_original')
        ]);

        // Act
        $controller = app(DeleteController::class);
        $deletedCount = $controller->removeMediaChunk($projectRef);

        // Assert: files were deleted
        $this->assertEquals($numOfFiles, $deletedCount);
        $this->assertCount(0, Storage::disk('photo')->files($projectRef));
        $this->assertCount(0, Storage::disk('photo')->allFiles($projectRef));
        $this->assertCount(0, Storage::disk('photo')->directories($projectRef));
        // The directory should also be removed
        $this->assertFalse(Storage::disk('photo')->exists($projectRef));
    }

    /**
     * @throws Exception
     */
    public function test_remove_media_chunk_deletes_from_all_local_disks()
    {
        $projectRef = Generators::projectRef();
        $uuid = Uuid::uuid4()->toString();

        // Fake each disk to point to a temporary directory
        foreach (config('epicollect.media.entries_deletable') as $disk) {
            Storage::fake($disk); // This creates a tmp path and overrides disk config
            Storage::disk($disk)->put("$projectRef/$uuid.test", 'some content');
        }

        $controller = app(DeleteController::class);
        $deletedCount = $controller->removeMediaChunk($projectRef);

        // We had 4 files total (1 per disk)
        $this->assertEquals(3, $deletedCount);

        foreach (config('epicollect.media.entries_deletable') as $disk) {
            $this->assertFalse(Storage::disk($disk)->exists("$projectRef/test.test"));
        }
    }

    /**
     * @throws Exception
     */
    public function test_remove_media_chunk_deletes_max_1000_local_files_single_folder()
    {
        $projectRef = Generators::projectRef();
        $uuid = Uuid::uuid4()->toString();
        $maxFiles   = config('epicollect.setup.bulk_deletion.chunk_size_media');

        // Only testing one disk here, but you can loop through all if needed
        $diskName = 'photo';
        Storage::fake($diskName);

        // Create 1500 files under this projectRef
        for ($i = 1; $i <= 1500; $i++) {
            Storage::disk($diskName)->put("$projectRef/$uuid.$i.jpg", 'dummy content');
        }

        // Ensure config points to only this disk
        config()->set('epicollect.media.entries_deletable', [$diskName]);

        // Run
        $controller = app(DeleteController::class);
        $deletedCount = $controller->removeMediaChunk($projectRef);

        // ✅ Assert only maxFiles deleted
        $this->assertEquals($maxFiles, $deletedCount, 'Should only delete up to max files per call');

        // ✅ Assert there are still files left after first chunk
        $remainingFiles = Storage::disk($diskName)->allFiles($projectRef);
        $this->assertCount(1500 - $maxFiles, $remainingFiles, 'Should leave remaining files for next call');
    }

    /**
     * @throws Exception
     */
    public function test_remove_media_chunk_deletes_max_1000_across_all_deletable_folders()
    {
        $projectRef = Generators::projectRef();
        $uuid = Uuid::uuid4()->toString();
        $maxFiles = config('epicollect.setup.bulk_deletion.chunk_size_media');
        $deletableDisks = config('epicollect.media.entries_deletable');

        // Fake all disks
        foreach ($deletableDisks as $diskName) {
            Storage::fake($diskName);
        }

        // Distribute > maxFiles files across all disks
        // e.g. 400 in each, totalling 1600 files
        $filesPerDisk = (int) ceil(($maxFiles * 1.6) / count($deletableDisks));
        foreach ($deletableDisks as $diskName) {
            for ($i = 1; $i <= $filesPerDisk; $i++) {
                Storage::disk($diskName)->put("$projectRef/$uuid.$i.dat", 'dummy content');
            }
        }

        // Ensure config points to all deletable disks
        config()->set('epicollect.media.entries_deletable', $deletableDisks);

        // Run deletion
        $controller = app(DeleteController::class);
        $deletedCount = $controller->removeMediaChunk($projectRef);

        // ✅ Assert only maxFiles deleted across all disks
        $this->assertEquals(
            $maxFiles,
            $deletedCount,
            'Should only delete up to max files per call across all folders'
        );

        // ✅ Count remaining files across all disks
        $totalRemaining = 0;
        foreach ($deletableDisks as $diskName) {
            $totalRemaining += count(Storage::disk($diskName)->allFiles($projectRef));
        }

        $this->assertGreaterThan(
            0,
            $totalRemaining,
            'Should leave remaining files for next call'
        );
        $this->assertEquals(($filesPerDisk * count($deletableDisks)) - $maxFiles, $totalRemaining);
    }






}
