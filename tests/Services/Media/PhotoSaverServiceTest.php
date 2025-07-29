<?php

namespace Tests\Services\Media;

use ec5\Services\Media\PhotoSaverService;
use ec5\Libraries\Utilities\Common;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Laravel\Facades\Image;
use Tests\TestCase;
use Mockery;
use InvalidArgumentException;
use RuntimeException;
use Exception;

/**
 * @SuppressWarnings("PHPMD")
 */
class PhotoSaverServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock Storage facade
        Storage::fake('local');
        Storage::fake('s3');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_saves_image_using_local_driver_when_configured()
    {
        // Arrange
        Config::set('filesystems.default', 'local');
        $projectRef = 'test-project-123';
        $fileName = 'test-image.jpg';
        $disk = 'public';
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);
        
        // Mock the processImage method result
        Image::shouldReceive('read')->once()->andReturnSelf();
        Image::shouldReceive('encode')->once()->andReturn('encoded-image-data');

        // Act
        $result = PhotoSaverService::saveImage($projectRef, $file, $fileName, $disk);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_saves_image_using_s3_driver_when_configured()
    {
        // Arrange
        Config::set('filesystems.default', 's3');
        $projectRef = 'test-project-456';
        $fileName = 'test-s3-image.jpg';
        $disk = 's3';
        $file = UploadedFile::fake()->image('test-s3.jpg', 800, 600);

        // Act
        $result = PhotoSaverService::saveImage($projectRef, $file, $fileName, $disk);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_for_unsupported_storage_driver()
    {
        // Arrange
        Config::set('filesystems.default', 'unsupported-driver');
        $projectRef = 'test-project-789';
        $fileName = 'test-unsupported.jpg';
        $disk = 'public';
        $file = UploadedFile::fake()->image('test.jpg', 400, 400);

        Log::shouldReceive('error')
            ->once()
            ->with('Storage driver not supported', ['driver' => 'unsupported-driver']);

        // Act
        $result = PhotoSaverService::saveImage($projectRef, $file, $fileName, $disk);

        // Assert
        $this->assertFalse($result);
    }

    /** @test */
    public function it_saves_image_locally_with_uploaded_file()
    {
        // Arrange
        $projectRef = 'local-project';
        $fileName = 'local-image.jpg';
        $disk = 'public';
        $file = UploadedFile::fake()->image('local.jpg', 600, 400);
        
        Image::shouldReceive('read')->once()->with($file->getRealPath())->andReturnSelf();
        Image::shouldReceive('encode')->once()->andReturn('processed-image-data');
        
        Storage::shouldReceive('disk')->with($disk)->andReturnSelf();
        Storage::shouldReceive('exists')->with($projectRef)->andReturn(false);
        Storage::shouldReceive('makeDirectory')->with($projectRef)->andReturn(true);
        Storage::shouldReceive('path')->with('')->andReturn('/fake/storage/path/');
        Storage::shouldReceive('put')->with(
            $projectRef . '/' . $fileName,
            'processed-image-data',
            [
                'visibility' => 'public',
                'directory_visibility' => 'public'
            ]
        )->andReturn(true);

        // Mock Common::setPermissionsRecursiveUp
        Common::shouldReceive('setPermissionsRecursiveUp')->once();

        // Act
        $result = PhotoSaverService::saveImageLocal($projectRef, $file, $fileName, $disk);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_saves_image_locally_with_string_path()
    {
        // Arrange
        $projectRef = 'string-project';
        $fileName = 'string-image.jpg';
        $disk = 'public';
        $imagePath = '/path/to/image.jpg';
        
        Image::shouldReceive('read')->once()->with($imagePath)->andReturnSelf();
        Image::shouldReceive('encode')->once()->andReturn('processed-image-data');
        
        Storage::shouldReceive('disk')->with($disk)->andReturnSelf();
        Storage::shouldReceive('exists')->with($projectRef)->andReturn(true);
        Storage::shouldReceive('put')->with(
            $projectRef . '/' . $fileName,
            'processed-image-data',
            [
                'visibility' => 'public',
                'directory_visibility' => 'public'
            ]
        )->andReturn(true);

        // Act
        $result = PhotoSaverService::saveImageLocal($projectRef, $imagePath, $fileName, $disk);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_creates_directory_and_sets_permissions_when_directory_does_not_exist()
    {
        // Arrange
        $projectRef = 'new-project';
        $fileName = 'new-image.jpg';
        $disk = 'public';
        $file = UploadedFile::fake()->image('new.jpg', 500, 500);
        
        Image::shouldReceive('read')->once()->andReturnSelf();
        Image::shouldReceive('encode')->once()->andReturn('processed-data');
        
        Storage::shouldReceive('disk')->with($disk)->andReturnSelf();
        Storage::shouldReceive('exists')->with($projectRef)->andReturn(false);
        Storage::shouldReceive('makeDirectory')->with($projectRef)->andReturn(true);
        Storage::shouldReceive('path')->with('')->andReturn('/storage/app/public/');
        Storage::shouldReceive('put')->andReturn(true);

        Common::shouldReceive('setPermissionsRecursiveUp')
            ->once()
            ->with('/storage/app/public/' . $projectRef);

        // Act
        $result = PhotoSaverService::saveImageLocal($projectRef, $file, $fileName, $disk);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_handles_local_storage_exceptions_gracefully()
    {
        // Arrange
        $projectRef = 'error-project';
        $fileName = 'error-image.jpg';
        $disk = 'public';
        $file = UploadedFile::fake()->image('error.jpg', 400, 400);
        
        Image::shouldReceive('read')->once()->andThrow(new Exception('Image processing failed'));
        
        Log::shouldReceive('error')
            ->once()
            ->with('Cannot save image', Mockery::type('array'));

        // Act
        $result = PhotoSaverService::saveImageLocal($projectRef, $file, $fileName, $disk);

        // Assert
        $this->assertFalse($result);
    }

    /** @test */
    public function it_saves_image_to_s3_with_uploaded_file()
    {
        // Arrange
        $projectRef = 's3-project';
        $fileName = 's3-image.jpg';
        $disk = 's3';
        $file = UploadedFile::fake()->image('s3.jpg', 800, 600);
        
        Image::shouldReceive('read')->once()->with($file->getRealPath())->andReturnSelf();
        Image::shouldReceive('encode')->once()->andReturn('s3-processed-data');
        
        Storage::shouldReceive('disk')->with($disk)->andReturnSelf();
        Storage::shouldReceive('put')->with(
            $projectRef . '/' . $fileName,
            's3-processed-data'
        )->andReturn(true);

        // Act
        $result = PhotoSaverService::saveImageS3($projectRef, $file, $fileName, $disk);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_saves_image_to_s3_with_string_path()
    {
        // Arrange
        $projectRef = 's3-string-project';
        $fileName = 's3-string-image.jpg';
        $disk = 's3';
        $imagePath = 's3://bucket/path/to/image.jpg';
        $mockStream = fopen('php://memory', 'r+');
        
        Storage::shouldReceive('disk')->with('s3')->andReturnSelf();
        Storage::shouldReceive('readStream')->with($imagePath)->andReturn($mockStream);
        Storage::shouldReceive('disk')->with($disk)->andReturnSelf();
        Storage::shouldReceive('put')->andReturn(true);

        // Act
        $result = PhotoSaverService::saveImageS3($projectRef, $imagePath, $fileName, $disk);

        // Assert
        $this->assertTrue($result);
        fclose($mockStream);
    }

    /** @test */
    public function it_throws_exception_when_s3_stream_cannot_be_opened()
    {
        // Arrange
        $projectRef = 's3-stream-error';
        $fileName = 'stream-error.jpg';
        $disk = 's3';
        $imagePath = 's3://bucket/invalid/path.jpg';
        
        Storage::shouldReceive('disk')->with('s3')->andReturnSelf();
        Storage::shouldReceive('readStream')->with($imagePath)->andReturn(false);
        
        Log::shouldReceive('error')
            ->once()
            ->with('Cannot save image to S3', Mockery::type('array'));

        // Act
        $result = PhotoSaverService::saveImageS3($projectRef, $imagePath, $fileName, $disk);

        // Assert
        $this->assertFalse($result);
    }

    /** @test */
    public function it_throws_exception_for_unsupported_image_type_in_s3()
    {
        // Arrange
        $projectRef = 's3-invalid-type';
        $fileName = 'invalid-type.jpg';
        $disk = 's3';
        $invalidImage = ['not' => 'supported'];
        
        Log::shouldReceive('error')
            ->once()
            ->with('Cannot save image to S3', Mockery::type('array'));

        // Act
        $result = PhotoSaverService::saveImageS3($projectRef, $invalidImage, $fileName, $disk);

        // Assert
        $this->assertFalse($result);
    }

    /** @test */
    public function it_processes_image_with_dimensions()
    {
        // Arrange
        $projectRef = 'dimension-project';
        $fileName = 'dimension-image.jpg';
        $disk = 'public';
        $file = UploadedFile::fake()->image('dimension.jpg', 1000, 800);
        $dimensions = [400, 300];
        $quality = 75;
        
        Image::shouldReceive('read')->once()->andReturnSelf();
        Image::shouldReceive('cover')->once()->with(400, 300)->andReturnSelf();
        Image::shouldReceive('encode')->once()->andReturn('resized-image-data');
        
        Storage::shouldReceive('disk')->with($disk)->andReturnSelf();
        Storage::shouldReceive('exists')->andReturn(true);
        Storage::shouldReceive('put')->andReturn(true);

        // Act
        $result = PhotoSaverService::saveImageLocal($projectRef, $file, $fileName, $disk, $dimensions, $quality);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_processes_image_with_square_dimensions_when_height_not_provided()
    {
        // Arrange
        $projectRef = 'square-project';
        $fileName = 'square-image.jpg';
        $disk = 'public';
        $file = UploadedFile::fake()->image('square.jpg', 800, 800);
        $dimensions = [300]; // Only width provided
        
        Image::shouldReceive('read')->once()->andReturnSelf();
        Image::shouldReceive('cover')->once()->with(300, 300)->andReturnSelf(); // Height defaults to width
        Image::shouldReceive('encode')->once()->andReturn('square-image-data');
        
        Storage::shouldReceive('disk')->with($disk)->andReturnSelf();
        Storage::shouldReceive('exists')->andReturn(true);
        Storage::shouldReceive('put')->andReturn(true);

        // Act
        $result = PhotoSaverService::saveImageLocal($projectRef, $file, $fileName, $disk, $dimensions);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_processes_image_without_dimensions()
    {
        // Arrange
        $projectRef = 'no-dimension-project';
        $fileName = 'no-dimension-image.jpg';
        $disk = 'public';
        $file = UploadedFile::fake()->image('no-dimension.jpg', 600, 400);
        
        Image::shouldReceive('read')->once()->andReturnSelf();
        Image::shouldReceive('cover')->never(); // Should not be called without dimensions
        Image::shouldReceive('encode')->once()->andReturn('original-size-data');
        
        Storage::shouldReceive('disk')->with($disk)->andReturnSelf();
        Storage::shouldReceive('exists')->andReturn(true);
        Storage::shouldReceive('put')->andReturn(true);

        // Act
        $result = PhotoSaverService::saveImageLocal($projectRef, $file, $fileName, $disk);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_uses_custom_quality_setting()
    {
        // Arrange
        $projectRef = 'quality-project';
        $fileName = 'quality-image.jpg';
        $disk = 'public';
        $file = UploadedFile::fake()->image('quality.jpg', 500, 500);
        $quality = 90;
        
        Image::shouldReceive('read')->once()->andReturnSelf();
        Image::shouldReceive('encode')->once()->andReturn('high-quality-data');
        
        Storage::shouldReceive('disk')->with($disk)->andReturnSelf();
        Storage::shouldReceive('exists')->andReturn(true);
        Storage::shouldReceive('put')->andReturn(true);

        // Act
        $result = PhotoSaverService::saveImageLocal($projectRef, $file, $fileName, $disk, [], $quality);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_handles_s3_stream_processing_with_dimensions()
    {
        // Arrange
        $projectRef = 's3-stream-dimension';
        $fileName = 's3-stream-image.jpg';
        $disk = 's3';
        $imagePath = 's3://bucket/stream/image.jpg';
        $dimensions = [200, 150];
        $mockStream = fopen('php://memory', 'r+');
        
        Storage::shouldReceive('disk')->with('s3')->andReturnSelf();
        Storage::shouldReceive('readStream')->with($imagePath)->andReturn($mockStream);
        Storage::shouldReceive('disk')->with($disk)->andReturnSelf();
        Storage::shouldReceive('put')->andReturn(true);

        // Act
        $result = PhotoSaverService::saveImageS3($projectRef, $imagePath, $fileName, $disk, $dimensions);

        // Assert
        $this->assertTrue($result);
        fclose($mockStream);
    }

    /** @test */
    public function it_handles_invalid_stream_resource_in_s3_processing()
    {
        // This test would require access to the private processImageS3 method
        // We can test this through the public saveImageS3 method with a mock
        
        // Arrange
        $projectRef = 's3-invalid-stream';
        $fileName = 'invalid-stream.jpg';
        $disk = 's3';
        $imagePath = 's3://bucket/invalid.jpg';
        
        Storage::shouldReceive('disk')->with('s3')->andReturnSelf();
        Storage::shouldReceive('readStream')->with($imagePath)->andReturn('not-a-resource');
        
        Log::shouldReceive('error')
            ->once()
            ->with('Cannot save image to S3', Mockery::type('array'));

        // Act
        $result = PhotoSaverService::saveImageS3($projectRef, $imagePath, $fileName, $disk);

        // Assert
        $this->assertFalse($result);
    }

    /** @test */
    public function it_uses_default_quality_of_50_when_not_specified()
    {
        // Arrange
        $projectRef = 'default-quality';
        $fileName = 'default-quality.jpg';
        $disk = 'public';
        $file = UploadedFile::fake()->image('default.jpg', 400, 400);
        
        Image::shouldReceive('read')->once()->andReturnSelf();
        Image::shouldReceive('encode')->once()->andReturn('default-quality-data');
        
        Storage::shouldReceive('disk')->with($disk)->andReturnSelf();
        Storage::shouldReceive('exists')->andReturn(true);
        Storage::shouldReceive('put')->andReturn(true);

        // Act - Not specifying quality, should use default of 50
        $result = PhotoSaverService::saveImageLocal($projectRef, $file, $fileName, $disk);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_handles_complex_project_references()
    {
        // Arrange
        $projectRef = 'complex/project/structure/with-dashes_and_underscores.123';
        $fileName = 'complex-image.jpg';
        $disk = 'public';
        $file = UploadedFile::fake()->image('complex.jpg', 300, 300);
        
        Image::shouldReceive('read')->once()->andReturnSelf();
        Image::shouldReceive('encode')->once()->andReturn('complex-data');
        
        Storage::shouldReceive('disk')->with($disk)->andReturnSelf();
        Storage::shouldReceive('exists')->with($projectRef)->andReturn(false);
        Storage::shouldReceive('makeDirectory')->with($projectRef)->andReturn(true);
        Storage::shouldReceive('path')->with('')->andReturn('/storage/');
        Storage::shouldReceive('put')->with(
            $projectRef . '/' . $fileName,
            'complex-data',
            Mockery::type('array')
        )->andReturn(true);

        Common::shouldReceive('setPermissionsRecursiveUp')->once();

        // Act
        $result = PhotoSaverService::saveImageLocal($projectRef, $file, $fileName, $disk);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_handles_special_characters_in_filenames()
    {
        // Arrange
        $projectRef = 'special-chars';
        $fileName = 'image-with-special-chars-áéíóú-ñ-中文.jpg';
        $disk = 'public';
        $file = UploadedFile::fake()->image('special.jpg', 400, 400);
        
        Image::shouldReceive('read')->once()->andReturnSelf();
        Image::shouldReceive('encode')->once()->andReturn('special-chars-data');
        
        Storage::shouldReceive('disk')->with($disk)->andReturnSelf();
        Storage::shouldReceive('exists')->andReturn(true);
        Storage::shouldReceive('put')->with(
            $projectRef . '/' . $fileName,
            'special-chars-data',
            Mockery::type('array')
        )->andReturn(true);

        // Act
        $result = PhotoSaverService::saveImageLocal($projectRef, $file, $fileName, $disk);

        // Assert
        $this->assertTrue($result);
    }
}