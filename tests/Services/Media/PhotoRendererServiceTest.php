<?php

namespace Tests\Services\Media;

use ec5\Libraries\Utilities\Generators;
use ec5\Services\Media\PhotoRendererService;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;
use Tests\TestCase;

class PhotoRendererServiceTest extends TestCase
{
    protected PhotoRendererService $photoRendererService;
    protected string $testProjectRef;
    protected string $testFilename;

    protected function setUp(): void
    {
        parent::setUp();
        $this->photoRendererService = app(PhotoRendererService::class);
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

        $result = $this->photoRendererService->resolvePhotoPath($disk, $path);

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

        $result = $this->photoRendererService->resolvePhotoPath($disk, $jpegPath);

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

        $result = $this->photoRendererService->resolvePhotoPath($disk, $webpPath);

        $this->assertEquals($webpPath, $result);
        $this->assertFalse($disk->exists($jpegPath));
        $this->assertTrue($disk->exists($webpPath));
    }

    public function test_it_returns_null_when_neither_jpeg_nor_webp_exist()
    {
        $disk = Storage::disk('photo');
        $path = $this->testProjectRef . '/' . $this->testFilename . '.jpg';

        $result = $this->photoRendererService->resolvePhotoPath($disk, $path);

        $this->assertNull($result);
    }

    public function test_it_resolves_path_on_project_disk()
    {
        $disk = Storage::disk('project');
        $path = $this->testProjectRef . '/' . $this->testFilename . '.jpg';

        // Create a real JPEG file on project disk
        $fakeImage = UploadedFile::fake()->image('avatar.jpg', 100, 100);
        $disk->put($path, $fakeImage->getContent());

        $result = $this->photoRendererService->resolvePhotoPath($disk, $path);

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

        $result = $this->photoRendererService->getAsJpeg($disk, $path, 90);

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

        $result = $this->photoRendererService->getAsJpeg($disk, $webpPath, 90);

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
        $highQualityResult = $this->photoRendererService->getAsJpeg($disk, $webpPath, 95);

        // Convert with low quality
        $lowQualityResult = $this->photoRendererService->getAsJpeg($disk, $webpPath, 50);

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
        $thumbnailData = $this->photoRendererService->createThumbnail($imageContent, $width, $height, 70);

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
        $response = $this->photoRendererService->placeholderOrFallback(
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

        $response = $this->photoRendererService->placeholderOrFallback(
            $photoName
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->status());
        $this->assertNotEmpty($response->getContent());
    }

    public function test_it_returns_generic_placeholder_for_project_avatar()
    {
        $avatarFilename = config('epicollect.media.project_avatar.filename');

        $response = $this->photoRendererService->placeholderOrFallback(
            $avatarFilename
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->status());
    }

    public function test_it_returns_generic_placeholder_for_legacy_project_avatar()
    {
        $legacyAvatarFilename = config('epicollect.media.project_avatar.legacy_filename');

        $response = $this->photoRendererService->placeholderOrFallback(
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

        $result = $this->photoRendererService->resolvePhotoPath($disk, $nestedPath);

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

        $result = $this->photoRendererService->getAsJpeg($disk, $path, 90);

        $this->assertNotEmpty($result);
        $this->assertStringStartsWith("\xFF\xD8\xFF", $result);
    }

    public function test_it_preserves_exif_metadata_during_webp_to_jpeg_conversion()
    {
        $disk = Storage::disk('photo');
        $jpegPath = $this->testProjectRef . '/' . $this->testFilename . '.jpg';
        $webpPath = $this->testProjectRef . '/' . $this->testFilename . '.webp';

        // Use a real image with EXIF (shipped with many test bundles)
        // Or provide a fixture manually in tests/Fixtures/exif-sample.jpg
        $fixturePath = base_path('tests/Files/photo-with-exif.jpeg');
        if (!file_exists($fixturePath)) {
            $this->markTestSkipped('Missing EXIF fixture image');
        }

        // Copy to disk
        $disk->put($jpegPath, file_get_contents($fixturePath));

        // Read and check EXIF from the original
        $img = Image::read($disk->get($jpegPath));
        $originalExif = $img->exif()->toArray();

        if (empty($originalExif)) {
            $this->markTestSkipped('Fixture image has no EXIF data.');
        }

        // Convert to WebP (keeping EXIF)
        $webpEncoder = new WebpEncoder(quality: config('epicollect.media.quality.webp'), strip: false);
        $webpContent = $img->encode($webpEncoder);
        $disk->put($webpPath, (string) $webpContent);

        // Convert WebP back to JPEG using your service
        $jpegContent = $this->photoRendererService->getAsJpeg($disk, $webpPath, config('epicollect.media.quality.jpg'));

        $this->assertNotEmpty($jpegContent);
        $this->assertStringStartsWith("\xFF\xD8\xFF", $jpegContent, 'Result should be valid JPEG');

        // Verify EXIF after conversion
        $resultImg = Image::read($jpegContent);
        $resultExif = $resultImg->exif()->toArray();

        // Assert EXIF presence
        $this->assertNotEmpty($resultExif, 'Final JPEG should still have EXIF data');

        // Compare a few key fields
        foreach (['Make', 'Model', 'Software'] as $key) {
            if (isset($originalExif[$key])) {
                $this->assertArrayHasKey($key, $resultExif, "$key field should be preserved");
                $this->assertEquals($originalExif[$key], $resultExif[$key], "$key value should match");
            }
        }
        //Compare GPS data (including nested keys)
        if (isset($originalExif['GPS']) && is_array($originalExif['GPS'])) {
            $this->assertArrayHasKey('GPS', $resultExif, 'Final EXIF should contain GPS section');
            $resultGps = $resultExif['GPS'] ?? [];

            foreach ($originalExif['GPS'] as $gpsKey => $gpsValue) {
                $this->assertArrayHasKey($gpsKey, $resultGps, "GPS field '$gpsKey' should be preserved");
                $this->assertEquals(
                    $gpsValue,
                    $resultGps[$gpsKey],
                    "GPS field '$gpsKey' value should match after conversion"
                );
            }
        } else {
            $this->markTestSkipped('Fixture has no nested GPS EXIF data to verify.');
        }
    }

    public function test_it_works_with_both_photo_and_project_disks()
    {
        // Test with photo disk
        $photoDisk = Storage::disk('photo');
        $photoPath = $this->testProjectRef . '/' . $this->testFilename . '-photo.jpg';
        $fakePhoto = UploadedFile::fake()->image('photo.jpg', 100, 100);
        $photoDisk->put($photoPath, $fakePhoto->getContent());

        $photoResult = $this->photoRendererService->resolvePhotoPath($photoDisk, $photoPath);
        $this->assertEquals($photoPath, $photoResult);

        // Test with project disk
        $projectDisk = Storage::disk('project');
        $projectPath = $this->testProjectRef . '/' . $this->testFilename . '-avatar.jpg';
        $fakeAvatar = UploadedFile::fake()->image('avatar.jpg', 100, 100);
        $projectDisk->put($projectPath, $fakeAvatar->getContent());

        $projectResult = $this->photoRendererService->resolvePhotoPath($projectDisk, $projectPath);
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

            $jpegContent = $this->photoRendererService->getAsJpeg($disk, $webpPath, 90);

            $this->assertNotEmpty($jpegContent);
            $this->assertStringStartsWith("\xFF\xD8\xFF", $jpegContent);
        }
    }
}
