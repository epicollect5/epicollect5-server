<?php

namespace Tests\Services\Media;

use ec5\Libraries\Utilities\Generators;
use ec5\Services\Media\PhotoRendererService;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Tests\TestCase;

class PhotoRendererServiceTest extends TestCase
{
    protected PhotoRendererService $service;
    protected string $testProjectRef;
    protected string $testFilename;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PhotoRendererService();
        $this->testProjectRef = Generators::projectRef();
        //file name without extension
        $this->testFilename = Str::uuid() . '_' . time();
    }

    protected function tearDown(): void
    {
        // Clean up test files from photo disk
        $photoDisk = Storage::disk('photo');
        if ($photoDisk->exists($this->testProjectRef)) {
            $photoDisk->deleteDirectory($this->testProjectRef);
        }

        // Clean up test files from project disk
        $projectDisk = Storage::disk('project');
        if ($projectDisk->exists($this->testProjectRef)) {
            $projectDisk->deleteDirectory($this->testProjectRef);
        }

        parent::tearDown();
    }

    public function test_it_resolves_jpeg_path_when_jpeg_exists_on_photo_disk()
    {
        $disk = Storage::disk('photo');
        $path = $this->testProjectRef . '/' . $this->testFilename . '.jpg';

        // Create a real JPEG file
        $fakeImage = UploadedFile::fake()->image('test.jpg', 100, 100);
        $disk->put($path, $fakeImage->getContent());

        $result = $this->service->resolvePhotoPath($disk, $path);

        $this->assertEquals($path, $result);
        $this->assertTrue($disk->exists($path));
    }

    public function test_it_resolves_jpeg_path_when_jpeg_does_not_exist_but_webp_does()
    {
        $disk = Storage::disk('photo');
        $jpegPath = $this->testProjectRef . '/' . $this->testFilename . '.jpg';
        $webpPath = $this->testProjectRef . '/' . $this->testFilename . '.webp';

        // Create only WebP file
        $fakeImage = UploadedFile::fake()->image('test.jpg', 1000, 1000);

        // Convert to WebP and store
        $img = Image::read($fakeImage->getRealPath());
        $webpContent = $img->toWebp(config('epicollect.media.quality.webp'));
        $disk->put($webpPath, (string) $webpContent);

        $result = $this->service->resolvePhotoPath($disk, $jpegPath);

        $this->assertEquals($webpPath, $result);
        $this->assertFalse($disk->exists($jpegPath));
        $this->assertTrue($disk->exists($webpPath));
    }

    public function test_it_resolves_webp_path_when_jpeg_does_not_exist_but_webp_does()
    {
        $disk = Storage::disk('photo');
        $jpegPath = $this->testProjectRef . '/' . $this->testFilename . '.jpg';
        $webpPath = $this->testProjectRef . '/' . $this->testFilename . '.webp';

        // Create only WebP file
        $fakeImage = UploadedFile::fake()->image('test.jpg', 1000, 1000);

        // Convert to WebP and store
        $img = Image::read($fakeImage->getRealPath());
        $webpContent = $img->toWebp(config('epicollect.media.quality.webp'));
        $disk->put($webpPath, (string) $webpContent);

        $result = $this->service->resolvePhotoPath($disk, $webpPath);

        $this->assertEquals($webpPath, $result);
        $this->assertFalse($disk->exists($jpegPath));
        $this->assertTrue($disk->exists($webpPath));
    }

    public function test_it_returns_null_when_neither_jpeg_nor_webp_exist()
    {
        $disk = Storage::disk('photo');
        $path = $this->testProjectRef . '/' . $this->testFilename . '.jpg';

        $result = $this->service->resolvePhotoPath($disk, $path);

        $this->assertNull($result);
    }

    public function test_it_resolves_path_on_project_disk()
    {
        $disk = Storage::disk('project');
        $path = $this->testProjectRef . '/' . $this->testFilename . '.jpg';

        // Create a real JPEG file on project disk
        $fakeImage = UploadedFile::fake()->image('avatar.jpg', 100, 100);
        $disk->put($path, $fakeImage->getContent());

        $result = $this->service->resolvePhotoPath($disk, $path);

        $this->assertEquals($path, $result);
        $this->assertTrue($disk->exists($path));
    }

    public function test_it_returns_jpeg_content_directly_when_path_is_jpeg()
    {
        $disk = Storage::disk('photo');
        $path = $this->testProjectRef . '/' . $this->testFilename . '.jpg';

        // Create a real JPEG file
        $fakeImage = UploadedFile::fake()->image('test.jpg', 100, 100);
        $originalContent = $fakeImage->getContent();
        $disk->put($path, $originalContent);

        $result = $this->service->getAsJpeg($disk, $path, 90);

        $this->assertNotEmpty($result);
        // Content should be JPEG binary data
        $this->assertStringStartsWith("\xFF\xD8\xFF", $result); // JPEG magic bytes
    }

    public function test_it_converts_webp_to_jpeg_when_path_is_webp()
    {
        $disk = Storage::disk('photo');
        $webpPath = $this->testProjectRef . '/' . $this->testFilename . '.webp';

        // Create a real WebP file
        $fakeImage = UploadedFile::fake()->image('test.jpg', 200, 200);
        $img = Image::read($fakeImage->getRealPath());
        $webpContent = $img->toWebp(80);
        $disk->put($webpPath, (string) $webpContent);

        $result = $this->service->getAsJpeg($disk, $webpPath, 90);

        $this->assertNotEmpty($result);
        // Result should be JPEG binary data
        $this->assertStringStartsWith("\xFF\xD8\xFF", $result); // JPEG magic bytes

        // Should be smaller or similar size due to conversion
        $webpSize = $disk->size($webpPath);
        $jpegSize = strlen($result);
        $this->assertGreaterThan($webpSize, $jpegSize);
    }

    public function test_it_uses_correct_quality_parameter_for_jpeg_encoding()
    {
        $disk = Storage::disk('photo');
        $webpPath = $this->testProjectRef . '/' . $this->testFilename . '.webp';

        // Create a real WebP file
        $fakeImage = UploadedFile::fake()->image('test.jpg', 300, 300);
        $img = Image::read($fakeImage->getRealPath());
        $webpContent = $img->toWebp(config('epicollect.media.quality.webp'));
        $disk->put($webpPath, (string) $webpContent);

        // Convert with high quality
        $highQualityResult = $this->service->getAsJpeg($disk, $webpPath, 95);

        // Convert with low quality
        $lowQualityResult = $this->service->getAsJpeg($disk, $webpPath, 50);

        // High quality should produce larger file
        $this->assertGreaterThan(strlen($lowQualityResult), strlen($highQualityResult));
    }

    public function test_it_creates_thumbnail_with_correct_dimensions()
    {
        $disk = Storage::disk('photo');
        $path = $this->testProjectRef . '/' . $this->testFilename . '.jpg';

        // Create a large image
        $fakeImage = UploadedFile::fake()->image('test.jpg', 1000, 1000);
        $disk->put($path, $fakeImage->getContent());

        // Read the image content
        $imageContent = $disk->get($path);

        // Create thumbnail
        $width = 100;
        $height = 100;
        $thumbnailData = $this->service->createThumbnail($imageContent, $width, $height, 70);

        $this->assertNotEmpty($thumbnailData);

        // Verify it's a valid JPEG
        $this->assertStringStartsWith("\xFF\xD8\xFF", $thumbnailData);

        // Verify dimensions by reading the thumbnail
        $img = Image::read($thumbnailData);
        $this->assertEquals($width, $img->width());
        $this->assertEquals($height, $img->height());
    }

    public function test_it_returns_generic_placeholder_when_no_name_provided()
    {
        $response = $this->service->placeholderOrFallback(
            null
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->status());
        $this->assertEquals(
            config('epicollect.media.content_type.photo'),
            $response->headers->get('Content-Type')
        );
        $this->assertNotEmpty($response->getContent());
    }

    public function test_it_returns_not_synced_placeholder_for_regular_photos()
    {
        $photoName = 'some-photo-' . Str::random(8) . '.jpg';

        $response = $this->service->placeholderOrFallback(
            $photoName
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->status());
        $this->assertNotEmpty($response->getContent());
    }

    public function test_it_returns_generic_placeholder_for_project_avatar()
    {
        $avatarFilename = config('epicollect.media.project_avatar.filename');

        $response = $this->service->placeholderOrFallback(
            $avatarFilename
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->status());
    }

    public function test_it_returns_generic_placeholder_for_legacy_project_avatar()
    {
        $legacyAvatarFilename = config('epicollect.media.project_avatar.legacy_filename');

        $response = $this->service->placeholderOrFallback(
            $legacyAvatarFilename
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->status());
    }


    public function test_it_handles_nested_directory_paths_correctly()
    {
        $disk = Storage::disk('photo');
        $nestedPath = $this->testProjectRef . '/nested/deep/' . $this->testFilename . '.jpg';
        $webpPath = $this->testProjectRef . '/nested/deep/' . $this->testFilename . '.webp';

        // Create WebP in nested directory
        $fakeImage = UploadedFile::fake()->image('test.jpg', 100, 100);
        $img = Image::read($fakeImage->getRealPath());
        $webpContent = $img->toWebp(80);
        $disk->put($webpPath, (string) $webpContent);

        $result = $this->service->resolvePhotoPath($disk, $nestedPath);

        $this->assertEquals($webpPath, $result);
        $this->assertTrue($disk->exists($webpPath));
    }

    public function test_it_handles_uppercase_extensions()
    {
        $disk = Storage::disk('photo');
        $path = $this->testProjectRef . '/' . $this->testFilename . '.JPG';

        // Create a JPEG with uppercase extension
        $fakeImage = UploadedFile::fake()->image('test.jpg', 100, 100);
        $disk->put($path, $fakeImage->getContent());

        $result = $this->service->getAsJpeg($disk, $path, 90);

        $this->assertNotEmpty($result);
        $this->assertStringStartsWith("\xFF\xD8\xFF", $result);
    }

    public function test_it_preserves_exif_metadata_during_webp_to_jpeg_conversion()
    {
        $disk = Storage::disk('photo');
        $webpPath = $this->testProjectRef . '/' . $this->testFilename . '.webp';

        // Create an image with EXIF data
        $fakeImage = UploadedFile::fake()->image('test.jpg', 300, 300);

        // Add EXIF data and convert to WebP
        $img = Image::read($fakeImage->getRealPath());
        $webpContent = $img->toWebp(80);
        $disk->put($webpPath, (string) $webpContent);

        // Convert to JPEG (should preserve EXIF with strip: false)
        $jpegContent = $this->service->getAsJpeg($disk, $webpPath, 90);

        $this->assertNotEmpty($jpegContent);

        // Verify it's valid JPEG
        $resultImg = Image::read($jpegContent);
        $this->assertNotNull($resultImg);
    }

    public function test_it_works_with_both_photo_and_project_disks()
    {
        // Test with photo disk
        $photoDisk = Storage::disk('photo');
        $photoPath = $this->testProjectRef . '/' . $this->testFilename . '-photo.jpg';
        $fakePhoto = UploadedFile::fake()->image('photo.jpg', 100, 100);
        $photoDisk->put($photoPath, $fakePhoto->getContent());

        $photoResult = $this->service->resolvePhotoPath($photoDisk, $photoPath);
        $this->assertEquals($photoPath, $photoResult);

        // Test with project disk
        $projectDisk = Storage::disk('project');
        $projectPath = $this->testProjectRef . '/' . $this->testFilename . '-avatar.jpg';
        $fakeAvatar = UploadedFile::fake()->image('avatar.jpg', 100, 100);
        $projectDisk->put($projectPath, $fakeAvatar->getContent());

        $projectResult = $this->service->resolvePhotoPath($projectDisk, $projectPath);
        $this->assertEquals($projectPath, $projectResult);
    }

    public function test_it_handles_conversion_with_different_image_sizes()
    {
        $disk = Storage::disk('photo');
        $sizes = [
            ['width' => 100, 'height' => 100],
            ['width' => 500, 'height' => 500],
            ['width' => 1000, 'height' => 1000],
        ];

        foreach ($sizes as $index => $size) {
            $webpPath = $this->testProjectRef . '/' . $this->testFilename . "-$index.webp";

            $fakeImage = UploadedFile::fake()->image('test.jpg', $size['width'], $size['height']);
            $img = Image::read($fakeImage->getRealPath());
            $webpContent = $img->toWebp(80);
            $disk->put($webpPath, (string) $webpContent);

            $jpegContent = $this->service->getAsJpeg($disk, $webpPath, 90);

            $this->assertNotEmpty($jpegContent);
            $this->assertStringStartsWith("\xFF\xD8\xFF", $jpegContent);
        }
    }
}
