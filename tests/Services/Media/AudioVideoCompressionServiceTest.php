<?php

namespace Tests\Services\Media;

use ec5\Services\Media\AudioVideoCompressionService;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Tests\TestCase;
use Throwable;

class AudioVideoCompressionServiceTest extends TestCase
{
    private AudioVideoCompressionService $service;
    private string $sampleMediaPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AudioVideoCompressionService();
        $this->sampleMediaPath = base_path('tests/Files/ffmpeg');
    }

    protected function tearDown(): void
    {
        // Clean up any test files created
        $this->cleanupTestFiles('video', ['test_720p.mp4','test_360p.mp4']);
        $this->cleanupTestFiles('audio', ['test_stereo.mp4', 'test_mono.mp4']);

        parent::tearDown();
    }

    public function test_it_compresses_1080p_video_to_720p_portrait()
    {
        // Copy fixture to test location
        $sourceFilename = 'video_1080x1920.mp4';
        $destinationFilename = 'test_720.mp4';
        $this->copySampleMediaToStorage($sourceFilename, 'video', $destinationFilename);

        $originalSize = Storage::disk('video')->size($destinationFilename);

        $result = $this->service->compress('video', $destinationFilename, 'video');

        $this->assertTrue($result);
        $this->assertTrue(Storage::disk('video')->exists($destinationFilename));

        // Compressed file should be smaller
        $compressedSize = Storage::disk('video')->size($destinationFilename);
        $this->assertLessThan($originalSize, $compressedSize);

        // Verify it's actually 720p now
        $this->assertVideoIs720pOrLess('video', $destinationFilename);
    }

    public function test_it_compresses_1080p_video_to_720p_landscape()
    {
        // Copy fixture to test location
        $sourceFilename = 'video_1920x1080.mp4';
        $destinationFilename = 'test_to_720.mp4';
        $this->copySampleMediaToStorage($sourceFilename, 'video', $destinationFilename);

        $result = $this->service->compress('video', $destinationFilename, 'video');

        $this->assertTrue($result);
        $this->assertTrue(Storage::disk('video')->exists($destinationFilename));

        // Verify it's actually 720p now
        $this->assertVideoIs720pOrLess('video', $destinationFilename);
    }

    public function test_it_skips_video_already_at_720p_landscape()
    {
        // Copy 720p video fixture
        $this->copySampleMediaToStorage('video_1280x720.mp4', 'video', 'test_720p.mp4');

        $originalSize = Storage::disk('video')->size('test_720p.mp4');

        $result = $this->service->compress('video', 'test_720p.mp4', 'video');

        $this->assertTrue($result);

        // Size should remain the same (not recompressed)
        $this->assertEquals($originalSize, Storage::disk('video')->size('test_720p.mp4'));
    }

    public function test_it_skips_video_less_than_720p_landscape()
    {
        // Copy  video fixture
        $this->copySampleMediaToStorage('video_640x360.mp4', 'video', 'test_360p.mp4');

        $originalSize = Storage::disk('video')->size('test_360p.mp4');

        $result = $this->service->compress('video', 'test_360p.mp4', 'video');

        $this->assertTrue($result);

        // Size should remain the same (not recompressed)
        $this->assertEquals($originalSize, Storage::disk('video')->size('test_360p.mp4'));
    }

    public function test_it_skips_video_already_at_720p_portrait()
    {
        // Copy 720p video fixture
        $this->copySampleMediaToStorage('video_720x1280.mp4', 'video', 'test_720p.mp4');

        $originalSize = Storage::disk('video')->size('test_720p.mp4');

        $result = $this->service->compress('video', 'test_720p.mp4', 'video');

        $this->assertTrue($result);

        // Size should remain the same (not recompressed)
        $this->assertEquals($originalSize, Storage::disk('video')->size('test_720p.mp4'));
    }

    public function test_it_compresses_stereo_audio_mp4_to_mono()
    {
        // Copy stereo audio fixture
        $this->copySampleMediaToStorage('audio.mp4', 'audio', 'test_stereo.mp4');

        $originalSize = Storage::disk('audio')->size('test_stereo.mp4');

        $result = $this->service->compress('audio', 'test_stereo.mp4', 'audio');

        $this->assertTrue($result);

        // Should be compressed to smaller size
        $compressedSize = Storage::disk('audio')->size('test_stereo.mp4');
        $this->assertLessThan($originalSize, $compressedSize);

        // Verify it's mono now
        $this->assertAudioIsMono('audio', 'test_stereo.mp4');
    }

    public function test_it_compresses_stereo_audio_wav_to_mono()
    {
        // Copy stereo audio fixture
        $this->copySampleMediaToStorage('audio.wav', 'audio', 'test_stereo.mp4');

        $originalSize = Storage::disk('audio')->size('test_stereo.mp4');

        $result = $this->service->compress('audio', 'test_stereo.mp4', 'audio');

        $this->assertTrue($result);

        // Should be compressed to smaller size
        $compressedSize = Storage::disk('audio')->size('test_stereo.mp4');
        $this->assertLessThan($originalSize, $compressedSize);

        // Verify it's mono now
        $this->assertAudioIsMono('audio', 'test_stereo.mp4');
    }

    public function test_it_skips_already_compressed_mono_audio()
    {
        // Copy mono audio fixture
        $this->copySampleMediaToStorage('audio_compressed.mp4', 'audio', 'test_mono.mp4');

        $originalSize = Storage::disk('audio')->size('test_mono.mp4');

        $result = $this->service->compress('audio', 'test_mono.mp4', 'audio');

        $this->assertTrue($result);

        // Size should remain the same
        $this->assertEquals($originalSize, Storage::disk('audio')->size('test_mono.mp4'));
    }

    private function copySampleMediaToStorage(string $fixtureFilename, string $disk, string $destinationFilename): void
    {
        $sourcePath = $this->sampleMediaPath . DIRECTORY_SEPARATOR . $fixtureFilename;

        if (!file_exists($sourcePath)) {
            $this->fail("Fixture file not found: {$sourcePath}");
        }

        // Open a read stream for the local fixture file
        $stream = fopen($sourcePath, 'r');

        // putFileAs() or put() with a stream handles directory creation
        // and S3/Local abstraction automatically
        $success = Storage::disk($disk)->put(
            $destinationFilename,
            $stream
        );

        if (is_resource($stream)) {
            fclose($stream);
        }

        if (!$success) {
            $this->fail("Failed to write fixture to disk: {$disk}");
        }
    }

    private function cleanupTestFiles(string $disk, array $files): void
    {
        foreach ($files as $file) {
            if (Storage::disk($disk)->exists($file)) {
                Storage::disk($disk)->delete($file);
            }
        }
    }

    private function assertVideoIs720pOrLess(string $disk, string $filename): void
    {
        try {
            // Use the package to open the file - it handles S3 vs Local abstraction
            $media = FFMpeg::fromDisk($disk)->open($filename);

            // Get the underlying video stream
            $videoStream = $media->getVideoStream();

            $width = (int) $videoStream->get('width');
            $height = (int) $videoStream->get('height');

            $shortSide = min($width, $height);

            $this->assertLessThanOrEqual(
                720,
                $shortSide,
                "Video dimensions {$width}x{$height} (short side: {$shortSide}px) exceed the 720p limit."
            );

        } catch (Throwable $e) {
            $this->fail('Could not get video dimensions: ' . $e->getMessage());
        }
    }

    private function assertAudioIsMono(string $disk, string $filename): void
    {
        $media = FFMpeg::fromDisk($disk)->open($filename);

        $streams = $media->getStreams(); // array of Stream objects

        $audioStream = null;

        foreach ($streams as $stream) {
            if ($stream->get('codec_type') === 'audio') {
                $audioStream = $stream;
                break;
            }
        }

        if (!$audioStream) {
            $this->fail('No audio stream found in media file');
        }

        $channels = (int) $audioStream->get('channels');

        if ($channels === 0) {
            $this->fail('Could not read audio channel information from stream');
        }

        $this->assertSame(
            1,
            $channels,
            'Audio should be mono (1 channel)'
        );
    }

}
