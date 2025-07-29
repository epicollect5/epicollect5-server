<?php

namespace Tests\Services\Media;

use ec5\Services\Media\AudioVideoSaverService;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.CamelCaseMethodName")
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
class AudioVideoSaverServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('s3');
        Storage::fake('entry_original');
        Storage::fake('entry_thumb');
        Log::spy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_saves_local_uploaded_file_successfully(): void
    {
        // Arrange
        $projectRef = 'test-project-123';
        $fileName = 'audio-file.mp3';
        $file = UploadedFile::fake()->create('test.mp3', 1024, 'audio/mpeg');
        $disk = 'local';
        
        // Act
        $result = AudioVideoSaverService::saveFile($projectRef, $file, $fileName, $disk, false);
        
        // Assert
        $this->assertTrue($result);
        Storage::disk($disk)->assertExists($projectRef . '/' . $fileName);
    }

    #[Test]
    public function it_saves_s3_file_to_local_disk_successfully(): void
    {
        // Arrange
        $projectRef = 'test-project-456';
        $fileName = 'video-file.mp4';
        $s3File = ['path' => 'source/video.mp4'];
        $disk = 'local';
        
        // Create a mock file in S3 storage
        Storage::disk('s3')->put($s3File['path'], 'fake video content');
        
        // Act
        $result = AudioVideoSaverService::saveFile($projectRef, $s3File, $fileName, $disk, true);
        
        // Assert
        $this->assertTrue($result);
        Storage::disk($disk)->assertExists($projectRef . '/' . $fileName);
    }

    #[Test]
    public function it_saves_s3_file_to_s3_disk_successfully(): void
    {
        // Arrange
        $projectRef = 'test-project-789';
        $fileName = 'audio-file.wav';
        $s3File = ['path' => 'source/audio.wav'];
        $disk = 's3';
        
        // Create a mock file in S3 storage
        Storage::disk('s3')->put($s3File['path'], 'fake audio content');
        
        // Act
        $result = AudioVideoSaverService::saveFile($projectRef, $s3File, $fileName, $disk, true);
        
        // Assert
        $this->assertTrue($result);
        Storage::disk($disk)->assertExists($projectRef . '/' . $fileName);
    }

    #[Test]
    public function it_creates_directory_when_saving_local_file(): void
    {
        // Arrange
        $projectRef = 'new-project-directory';
        $fileName = 'test-file.mp3';
        $file = UploadedFile::fake()->create('test.mp3', 1024, 'audio/mpeg');
        $disk = 'local';
        
        // Ensure directory doesn't exist initially
        Storage::disk($disk)->assertMissing($projectRef);
        
        // Act
        $result = AudioVideoSaverService::saveFile($projectRef, $file, $fileName, $disk, false);
        
        // Assert
        $this->assertTrue($result);
        Storage::disk($disk)->assertExists($projectRef . '/' . $fileName);
    }

    #[Test]
    public function it_sets_public_visibility_on_saved_files(): void
    {
        // Arrange
        $projectRef = 'visibility-test';
        $fileName = 'public-file.mp4';
        $file = UploadedFile::fake()->create('test.mp4', 2048, 'video/mp4');
        $disk = 'local';
        
        // Act
        $result = AudioVideoSaverService::saveFile($projectRef, $file, $fileName, $disk, false);
        
        // Assert
        $this->assertTrue($result);
        $this->assertEquals('public', Storage::disk($disk)->getVisibility($projectRef . '/' . $fileName));
    }

    #[Test]
    public function it_returns_false_when_s3_source_file_not_found(): void
    {
        // Arrange
        $projectRef = 'test-project';
        $fileName = 'missing-file.mp3';
        $s3File = ['path' => 'non-existent/file.mp3'];
        $disk = 'local';
        
        // Act
        $result = AudioVideoSaverService::saveFile($projectRef, $s3File, $fileName, $disk, true);
        
        // Assert
        $this->assertFalse($result);
        Log::shouldHaveReceived('error')
            ->with('Failed to read stream from S3', ['file' => 'non-existent/file.mp3'])
            ->once();
    }

    #[Test]
    public function it_returns_false_when_s3_file_path_is_empty(): void
    {
        // Arrange
        $projectRef = 'test-project';
        $fileName = 'empty-path.mp3';
        $s3File = ['path' => ''];
        $disk = 'local';
        
        // Act
        $result = AudioVideoSaverService::saveFile($projectRef, $s3File, $fileName, $disk, true);
        
        // Assert
        $this->assertFalse($result);
        Log::shouldHaveReceived('error')
            ->with('Failed to read stream from S3', ['file' => ''])
            ->once();
    }

    #[Test]
    public function it_returns_false_when_s3_file_has_no_path_key(): void
    {
        // Arrange
        $projectRef = 'test-project';
        $fileName = 'no-path.mp3';
        $s3File = ['other_key' => 'value'];
        $disk = 'local';
        
        // Act
        $result = AudioVideoSaverService::saveFile($projectRef, $s3File, $fileName, $disk, true);
        
        // Assert
        $this->assertFalse($result);
        Log::shouldHaveReceived('error')
            ->with('Failed to read stream from S3', ['file' => ''])
            ->once();
    }

    #[Test]
    public function it_handles_exception_and_logs_error(): void
    {
        // Arrange
        $projectRef = 'test-project';
        $fileName = 'test-file.mp3';
        $file = UploadedFile::fake()->create('test.mp3', 1024, 'audio/mpeg');
        $disk = 'invalid-disk'; // This should cause an exception
        
        // Act
        $result = AudioVideoSaverService::saveFile($projectRef, $file, $fileName, $disk, false);
        
        // Assert
        $this->assertFalse($result);
        Log::shouldHaveReceived('error')
            ->withArgs(function ($message, $context) use ($projectRef, $fileName, $disk) {
                return $message === 'Failed to save file' &&
                       $context['projectRef'] === $projectRef &&
                       $context['fileName'] === $fileName &&
                       $context['disk'] === $disk &&
                       $context['isS3'] === false &&
                       isset($context['exception']);
            })
            ->once();
    }

    #[Test]
    #[DataProvider('diskProvider')]
    public function it_works_with_different_storage_disks(string $disk): void
    {
        // Arrange
        Storage::fake($disk);
        $projectRef = 'multi-disk-test';
        $fileName = 'test-file.mp3';
        $file = UploadedFile::fake()->create('test.mp3', 1024, 'audio/mpeg');
        
        // Act
        $result = AudioVideoSaverService::saveFile($projectRef, $file, $fileName, $disk, false);
        
        // Assert
        $this->assertTrue($result);
        Storage::disk($disk)->assertExists($projectRef . '/' . $fileName);
    }

    #[Test]
    #[DataProvider('fileTypeProvider')]
    public function it_saves_various_file_types(string $originalName, string $mimeType): void
    {
        // Arrange
        $projectRef = 'file-type-test';
        $fileName = 'saved-' . $originalName;
        $file = UploadedFile::fake()->create($originalName, 1024, $mimeType);
        $disk = 'local';
        
        // Act
        $result = AudioVideoSaverService::saveFile($projectRef, $file, $fileName, $disk, false);
        
        // Assert
        $this->assertTrue($result);
        Storage::disk($disk)->assertExists($projectRef . '/' . $fileName);
    }

    #[Test]
    public function it_handles_large_files(): void
    {
        // Arrange
        $projectRef = 'large-file-test';
        $fileName = 'large-video.mp4';
        $file = UploadedFile::fake()->create('large.mp4', 50000, 'video/mp4'); // 50MB
        $disk = 'local';
        
        // Act
        $result = AudioVideoSaverService::saveFile($projectRef, $file, $fileName, $disk, false);
        
        // Assert
        $this->assertTrue($result);
        Storage::disk($disk)->assertExists($projectRef . '/' . $fileName);
    }

    #[Test]
    public function it_handles_files_with_special_characters_in_project_ref(): void
    {
        // Arrange
        $projectRef = 'project-with-spëcial-chars_123';
        $fileName = 'test-file.mp3';
        $file = UploadedFile::fake()->create('test.mp3', 1024, 'audio/mpeg');
        $disk = 'local';
        
        // Act
        $result = AudioVideoSaverService::saveFile($projectRef, $file, $fileName, $disk, false);
        
        // Assert
        $this->assertTrue($result);
        Storage::disk($disk)->assertExists($projectRef . '/' . $fileName);
    }

    #[Test]
    public function it_handles_nested_project_references(): void
    {
        // Arrange
        $projectRef = 'parent/child/grandchild';
        $fileName = 'nested-file.wav';
        $file = UploadedFile::fake()->create('test.wav', 1024, 'audio/wav');
        $disk = 'local';
        
        // Act
        $result = AudioVideoSaverService::saveFile($projectRef, $file, $fileName, $disk, false);
        
        // Assert
        $this->assertTrue($result);
        Storage::disk($disk)->assertExists($projectRef . '/' . $fileName);
    }

    #[Test]
    public function it_overwrites_existing_files_with_same_name(): void
    {
        // Arrange
        $projectRef = 'overwrite-test';
        $fileName = 'duplicate-name.mp3';
        $disk = 'local';
        
        $file1 = UploadedFile::fake()->create('original.mp3', 1024, 'audio/mpeg');
        $file2 = UploadedFile::fake()->create('replacement.mp3', 2048, 'audio/mpeg');
        
        // Save first file
        AudioVideoSaverService::saveFile($projectRef, $file1, $fileName, $disk, false);
        $originalSize = Storage::disk($disk)->size($projectRef . '/' . $fileName);
        
        // Act - Save second file with same name
        $result = AudioVideoSaverService::saveFile($projectRef, $file2, $fileName, $disk, false);
        
        // Assert
        $this->assertTrue($result);
        $newSize = Storage::disk($disk)->size($projectRef . '/' . $fileName);
        $this->assertNotEquals($originalSize, $newSize);
    }

    #[Test]
    public function it_handles_s3_to_s3_transfer(): void
    {
        // Arrange
        $projectRef = 's3-to-s3-test';
        $fileName = 'transferred-file.mp4';
        $sourceFile = ['path' => 'source/original.mp4'];
        $disk = 's3';
        
        // Create source file in S3
        Storage::disk('s3')->put($sourceFile['path'], 'original video content');
        
        // Act
        $result = AudioVideoSaverService::saveFile($projectRef, $sourceFile, $fileName, $disk, true);
        
        // Assert
        $this->assertTrue($result);
        Storage::disk($disk)->assertExists($projectRef . '/' . $fileName);
        $this->assertEquals(
            'original video content',
            Storage::disk($disk)->get($projectRef . '/' . $fileName)
        );
    }

    #[Test]
    public function it_handles_concurrent_saves_to_same_project(): void
    {
        // Arrange
        $projectRef = 'concurrent-test';
        $disk = 'local';
        $files = [
            ['file' => UploadedFile::fake()->create('file1.mp3', 1024, 'audio/mpeg'), 'name' => 'file1.mp3'],
            ['file' => UploadedFile::fake()->create('file2.mp4', 2048, 'video/mp4'), 'name' => 'file2.mp4'],
            ['file' => UploadedFile::fake()->create('file3.wav', 1536, 'audio/wav'), 'name' => 'file3.wav'],
        ];
        
        // Act
        $results = [];
        foreach ($files as $fileData) {
            $results[] = AudioVideoSaverService::saveFile(
                $projectRef,
                $fileData['file'],
                $fileData['name'],
                $disk,
                false
            );
        }
        
        // Assert
        foreach ($results as $result) {
            $this->assertTrue($result);
        }
        
        foreach ($files as $fileData) {
            Storage::disk($disk)->assertExists($projectRef . '/' . $fileData['name']);
        }
    }

    #[Test]
    public function it_maintains_file_integrity_during_transfer(): void
    {
        // Arrange
        $projectRef = 'integrity-test';
        $fileName = 'test-content.mp3';
        $disk = 'local';
        $expectedContent = 'This is test audio content with special chars: éñ™';
        
        // Create a file with specific content
        $tempFile = tmpfile();
        fwrite($tempFile, $expectedContent);
        $tempPath = stream_get_meta_data($tempFile)['uri'];
        
        $file = new UploadedFile($tempPath, 'test.mp3', 'audio/mpeg', null, true);
        
        // Act
        $result = AudioVideoSaverService::saveFile($projectRef, $file, $fileName, $disk, false);
        
        // Assert
        $this->assertTrue($result);
        $savedContent = Storage::disk($disk)->get($projectRef . '/' . $fileName);
        $this->assertEquals($expectedContent, $savedContent);
        
        // Cleanup
        fclose($tempFile);
    }

    public static function diskProvider(): array
    {
        return [
            ['local'],
            ['s3'],
            ['entry_original'],
            ['entry_thumb'],
        ];
    }

    public static function fileTypeProvider(): array
    {
        return [
            ['audio.mp3', 'audio/mpeg'],
            ['video.mp4', 'video/mp4'],
            ['audio.wav', 'audio/wav'],
            ['video.avi', 'video/x-msvideo'],
            ['audio.flac', 'audio/flac'],
            ['video.mov', 'video/quicktime'],
            ['audio.aac', 'audio/aac'],
            ['video.webm', 'video/webm'],
        ];
    }
}