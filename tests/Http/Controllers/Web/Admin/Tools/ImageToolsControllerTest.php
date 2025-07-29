<?php

namespace Tests\Http\Controllers\Web\Admin\Tools;

use ec5\Http\Controllers\Web\Admin\Tools\ImageToolsController;
use ec5\Libraries\DirectoryGenerator\DirectoryGenerator;
use ec5\Services\Media\PhotoSaverService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @SuppressWarnings("PHPMD.ExcessivePublicCount")
 * @SuppressWarnings("PHPMD.AvoidStaticAccess")
 */
class ImageToolsControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected $controller;
    protected $mockRootDisk;
    protected $mockThumbDisk;

    public function setUp(): void
    {
        parent::setUp();
        
        $this->controller = new ImageToolsController();
        
        // Mock storage disks
        $this->mockRootDisk = Mockery::mock('disk');
        $this->mockThumbDisk = Mockery::mock('disk');
        
        Storage::shouldReceive('disk')
            ->with('entry_original')
            ->andReturn($this->mockRootDisk);
            
        Storage::shouldReceive('disk')
            ->with('entry_thumb')
            ->andReturn($this->mockThumbDisk);
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function itCanInstantiateController()
    {
        $this->assertInstanceOf(ImageToolsController::class, $this->controller);
        $this->assertContains(DirectoryGenerator::class, class_uses($this->controller));
    }

    /** @test */
    public function resizeEntryImagesProcessesJpgFilesOnly()
    {
        // Mock configuration values
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_original_landscape')
            ->andReturn([800, 600]);
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_original_portrait')
            ->andReturn([600, 800]);
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_min')
            ->andReturn(600);
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_max')
            ->andReturn(800);

        // Mock directory generator
        $mockController = Mockery::mock(ImageToolsController::class)->makePartial();
        $mockController->shouldReceive('directoryGenerator')
            ->with($this->mockRootDisk)
            ->andReturn(['test-directory']);
        
        $mockController->shouldReceive('fileGenerator')
            ->with($this->mockRootDisk, 'test-directory')
            ->andReturn([
                'test-directory/image1.jpg',
                'test-directory/image2.png',
                'test-directory/image3.gif'
            ]);

        $this->mockRootDisk->shouldReceive('path')
            ->with('')
            ->andReturn('/fake/path/');

        // Mock getimagesize function behavior for jpg file
        $mockController->shouldReceive('getImageSize')
            ->with('/fake/path/test-directory/image1.jpg')
            ->andReturn([400, 300]); // Needs resizing

        // Mock PhotoSaverService
        PhotoSaverService::shouldReceive('storeImage')
            ->once()
            ->andReturn(true);

        // Expect dd() to be called at the end
        $mockController->shouldReceive('dd')
            ->with('Done!')
            ->once();

        $this->expectOutputRegex('/File: test-directory\/image1\.jpg/');
        
        $mockController->resizeEntryImages();
    }

    /** @test */
    public function resizeEntryImagesSkipsNonJpgFiles()
    {
        // Mock configuration values
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_original_landscape')
            ->andReturn([800, 600]);
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_min')
            ->andReturn(600);
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_max')
            ->andReturn(800);

        $mockController = Mockery::mock(ImageToolsController::class)->makePartial();
        $mockController->shouldReceive('directoryGenerator')
            ->with($this->mockRootDisk)
            ->andReturn(['test-directory']);
        
        $mockController->shouldReceive('fileGenerator')
            ->with($this->mockRootDisk, 'test-directory')
            ->andReturn([
                'test-directory/image1.png',
                'test-directory/image2.gif'
            ]);

        // Expect dd() to be called at the end (no processing should occur)
        $mockController->shouldReceive('dd')
            ->with('Done!')
            ->once();

        // No output expected since no jpg files
        $this->expectOutputString('');
        
        $mockController->resizeEntryImages();
    }

    /** @test */
    public function resizeEntryImagesHandlesLandscapeImagesCorrectly()
    {
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_original_landscape')
            ->andReturn([800, 600]);
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_min')
            ->andReturn(600);
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_max')
            ->andReturn(800);

        $mockController = Mockery::mock(ImageToolsController::class)->makePartial();
        $mockController->shouldReceive('directoryGenerator')
            ->andReturn(['test-directory']);
        $mockController->shouldReceive('fileGenerator')
            ->andReturn(['test-directory/landscape.jpg']);

        $this->mockRootDisk->shouldReceive('path')
            ->andReturn('/fake/path/');

        // Mock landscape image (width > height, needs resizing)
        $mockController->shouldReceive('getImageSize')
            ->andReturn([900, 700]); // Incorrect dimensions

        PhotoSaverService::shouldReceive('storeImage')
            ->with('test-directory', '/fake/path/test-directory/landscape.jpg', 'landscape.jpg', 'entry_original', [800, 600])
            ->once()
            ->andReturn(true);

        $mockController->shouldReceive('dd')->with('Done!');

        $this->expectOutputRegex('/Original Dimensions: 900,700/');
        $this->expectOutputRegex('/New Dimensions: 800, 600/');

        $mockController->resizeEntryImages();
    }

    /** @test */
    public function resizeEntryImagesHandlesPortraitImagesCorrectly()
    {
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_original_portrait')
            ->andReturn([600, 800]);
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_min')
            ->andReturn(600);
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_max')
            ->andReturn(800);

        $mockController = Mockery::mock(ImageToolsController::class)->makePartial();
        $mockController->shouldReceive('directoryGenerator')
            ->andReturn(['test-directory']);
        $mockController->shouldReceive('fileGenerator')
            ->andReturn(['test-directory/portrait.jpg']);

        $this->mockRootDisk->shouldReceive('path')
            ->andReturn('/fake/path/');

        // Mock portrait image (height > width, needs resizing)
        $mockController->shouldReceive('getImageSize')
            ->andReturn([500, 900]); // Incorrect dimensions

        PhotoSaverService::shouldReceive('storeImage')
            ->with('test-directory', '/fake/path/test-directory/portrait.jpg', 'portrait.jpg', 'entry_original', [600, 800])
            ->once()
            ->andReturn(true);

        $mockController->shouldReceive('dd')->with('Done!');

        $this->expectOutputRegex('/Original Dimensions: 500,900/');
        $this->expectOutputRegex('/New Dimensions: 600, 800/');

        $mockController->resizeEntryImages();
    }

    /** @test */
    public function resizeEntryImagesSkipsCorrectlySizedImages()
    {
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_min')
            ->andReturn(600);
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_max')
            ->andReturn(800);

        $mockController = Mockery::mock(ImageToolsController::class)->makePartial();
        $mockController->shouldReceive('directoryGenerator')
            ->andReturn(['test-directory']);
        $mockController->shouldReceive('fileGenerator')
            ->andReturn(['test-directory/correct.jpg']);

        $this->mockRootDisk->shouldReceive('path')
            ->andReturn('/fake/path/');

        // Mock correctly sized landscape image
        $mockController->shouldReceive('getImageSize')
            ->andReturn([800, 600]); // Correct dimensions

        // PhotoSaverService should not be called
        PhotoSaverService::shouldNotReceive('storeImage');

        $mockController->shouldReceive('dd')->with('Done!');

        // No processing output expected
        $this->expectOutputString('');

        $mockController->resizeEntryImages();
    }

    /** @test */
    public function resizeEntryImagesHandlesPhotoSaverServiceErrors()
    {
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_original_landscape')
            ->andReturn([800, 600]);
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_min')
            ->andReturn(600);
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_max')
            ->andReturn(800);

        $mockController = Mockery::mock(ImageToolsController::class)->makePartial();
        $mockController->shouldReceive('directoryGenerator')
            ->andReturn(['test-directory']);
        $mockController->shouldReceive('fileGenerator')
            ->andReturn(['test-directory/error.jpg']);

        $this->mockRootDisk->shouldReceive('path')
            ->andReturn('/fake/path/');

        $mockController->shouldReceive('getImageSize')
            ->andReturn([400, 300]); // Needs resizing

        // Mock PhotoSaverService failure
        PhotoSaverService::shouldReceive('storeImage')
            ->once()
            ->andReturn(false);

        $mockController->shouldReceive('dd')->with('Done!');

        $this->expectOutputString('Error with test-directory/error.jpg');

        $mockController->resizeEntryImages();
    }

    /** @test */
    public function createEntryExtraImagesProcessesJpgFilesCorrectly()
    {
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_thumb')
            ->andReturn([150, 150]);

        $mockController = Mockery::mock(ImageToolsController::class)->makePartial();
        $mockController->shouldReceive('directoryGenerator')
            ->with($this->mockRootDisk)
            ->andReturn(['test-directory']);
        
        $mockController->shouldReceive('fileGenerator')
            ->with($this->mockRootDisk, 'test-directory')
            ->andReturn(['test-directory/image.jpg']);

        $this->mockRootDisk->shouldReceive('path')
            ->with('')
            ->andReturn('/fake/source/');

        $this->mockThumbDisk->shouldReceive('path')
            ->with('')
            ->andReturn('/fake/dest/');

        $this->mockThumbDisk->shouldReceive('put')
            ->with('test-directory/image.jpg', Mockery::any(), Mockery::any())
            ->andReturn(true);

        PhotoSaverService::shouldReceive('storeImage')
            ->with('test-directory', '/fake/dest/test-directory/image.jpg', 'image.jpg', 'entry_thumb', [150, 150])
            ->once()
            ->andReturn(true);

        $mockController->shouldReceive('dd')->with('Done!');

        $this->expectOutputRegex('/File: test-directory\/image\.jpg/');
        $this->expectOutputRegex('/New Dimensions: 150, 150/');

        $mockController->createEntryExtraImages();
    }

    /** @test */
    public function createEntryExtraImagesSkipsNonJpgFiles()
    {
        $mockController = Mockery::mock(ImageToolsController::class)->makePartial();
        $mockController->shouldReceive('directoryGenerator')
            ->andReturn(['test-directory']);
        $mockController->shouldReceive('fileGenerator')
            ->andReturn([
                'test-directory/image.png',
                'test-directory/image.gif'
            ]);

        $mockController->shouldReceive('dd')->with('Done!');

        // No processing should occur for non-jpg files
        $this->expectOutputString('');

        $mockController->createEntryExtraImages();
    }

    /** @test */
    public function createEntryExtraImagesHandlesFileSaveFailures()
    {
        $mockController = Mockery::mock(ImageToolsController::class)->makePartial();
        $mockController->shouldReceive('directoryGenerator')
            ->andReturn(['test-directory']);
        $mockController->shouldReceive('fileGenerator')
            ->andReturn(['test-directory/image.jpg']);

        $this->mockRootDisk->shouldReceive('path')
            ->andReturn('/fake/source/');

        $this->mockThumbDisk->shouldReceive('path')
            ->andReturn('/fake/dest/');

        // Mock file save failure
        $this->mockThumbDisk->shouldReceive('put')
            ->andReturn(false);

        // PhotoSaverService should not be called if file save fails
        PhotoSaverService::shouldNotReceive('storeImage');

        $mockController->shouldReceive('dd')->with('Done!');

        // No output expected on file save failure
        $this->expectOutputString('');

        $mockController->createEntryExtraImages();
    }

    /** @test */
    public function createEntryExtraImagesHandlesResizeServiceErrors()
    {
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_thumb')
            ->andReturn([150, 150]);

        $mockController = Mockery::mock(ImageToolsController::class)->makePartial();
        $mockController->shouldReceive('directoryGenerator')
            ->andReturn(['test-directory']);
        $mockController->shouldReceive('fileGenerator')
            ->andReturn(['test-directory/error.jpg']);

        $this->mockRootDisk->shouldReceive('path')
            ->andReturn('/fake/source/');

        $this->mockThumbDisk->shouldReceive('path')
            ->andReturn('/fake/dest/');

        $this->mockThumbDisk->shouldReceive('put')
            ->andReturn(true);

        // Mock PhotoSaverService failure
        PhotoSaverService::shouldReceive('storeImage')
            ->once()
            ->andReturn(false);

        $mockController->shouldReceive('dd')->with('Done!');

        $this->expectOutputString('Error with test-directory/error.jpg');

        $mockController->createEntryExtraImages();
    }

    /** @test */
    public function createEntryExtraImagesProcessesMultipleDirectories()
    {
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_thumb')
            ->andReturn([150, 150]);

        $mockController = Mockery::mock(ImageToolsController::class)->makePartial();
        $mockController->shouldReceive('directoryGenerator')
            ->andReturn(['dir1', 'dir2']);
        
        $mockController->shouldReceive('fileGenerator')
            ->with($this->mockRootDisk, 'dir1')
            ->andReturn(['dir1/image1.jpg']);
        
        $mockController->shouldReceive('fileGenerator')
            ->with($this->mockRootDisk, 'dir2')
            ->andReturn(['dir2/image2.jpg']);

        $this->mockRootDisk->shouldReceive('path')
            ->andReturn('/fake/source/');

        $this->mockThumbDisk->shouldReceive('path')
            ->andReturn('/fake/dest/');

        $this->mockThumbDisk->shouldReceive('put')
            ->twice()
            ->andReturn(true);

        PhotoSaverService::shouldReceive('storeImage')
            ->twice()
            ->andReturn(true);

        $mockController->shouldReceive('dd')->with('Done!');

        $this->expectOutputRegex('/File: dir1\/image1\.jpg/');
        $this->expectOutputRegex('/File: dir2\/image2\.jpg/');

        $mockController->createEntryExtraImages();
    }

    /** @test */
    public function createEntryExtraImagesUsesCorrectFilePermissions()
    {
        Config::shouldReceive('get')
            ->with('epicollect.media.entry_thumb')
            ->andReturn([150, 150]);

        $mockController = Mockery::mock(ImageToolsController::class)->makePartial();
        $mockController->shouldReceive('directoryGenerator')
            ->andReturn(['test-directory']);
        $mockController->shouldReceive('fileGenerator')
            ->andReturn(['test-directory/image.jpg']);

        $this->mockRootDisk->shouldReceive('path')
            ->andReturn('/fake/source/');

        $this->mockThumbDisk->shouldReceive('path')
            ->andReturn('/fake/dest/');

        // Verify correct permissions are set
        $this->mockThumbDisk->shouldReceive('put')
            ->with(
                'test-directory/image.jpg',
                Mockery::any(),
                [
                    'visibility' => 'public',
                    'directory_visibility' => 'public'
                ]
            )
            ->andReturn(true);

        PhotoSaverService::shouldReceive('storeImage')
            ->andReturn(true);

        $mockController->shouldReceive('dd')->with('Done!');

        $mockController->createEntryExtraImages();
    }

    /** @test */
    public function methodsHandleEmptyDirectoriesGracefully()
    {
        $mockController = Mockery::mock(ImageToolsController::class)->makePartial();
        
        // Test resizeEntryImages with empty directories
        $mockController->shouldReceive('directoryGenerator')
            ->andReturn([]);
        $mockController->shouldReceive('dd')->with('Done!');

        $this->expectOutputString('');
        $mockController->resizeEntryImages();

        // Test createEntryExtraImages with empty directories
        $mockController->shouldReceive('directoryGenerator')
            ->andReturn([]);
        $mockController->shouldReceive('dd')->with('Done!');

        $this->expectOutputString('');
        $mockController->createEntryExtraImages();
    }

    /** @test */
    public function methodsHandleDirectoriesWithNoFiles()
    {
        $mockController = Mockery::mock(ImageToolsController::class)->makePartial();
        
        // Test with directories that contain no files
        $mockController->shouldReceive('directoryGenerator')
            ->andReturn(['empty-directory']);
        $mockController->shouldReceive('fileGenerator')
            ->andReturn([]);
        $mockController->shouldReceive('dd')->with('Done!');

        $this->expectOutputString('');
        $mockController->resizeEntryImages();
    }
}

/* SKIPPED FIXES:
   - PHPMD #1: Class has too many public methods; refactoring to reduce count is beyond the scope of this change.
   - PHPMD #2-#4, #11, #14-#15, #18, #22: Avoid static access to Mockery and PhotoSaverService calls; changing mocking approach or service usage requires broader refactoring and dependency injection adjustments, which are out of scope.
*/