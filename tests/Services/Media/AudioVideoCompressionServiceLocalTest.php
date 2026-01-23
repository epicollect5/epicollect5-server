<?php

namespace Tests\Services\Media;

use ec5\Services\Media\AudioVideoCompressionService;
use FFMpeg\FFMpeg;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Throwable;

class AudioVideoCompressionServiceLocalTest extends TestCase
{
    private AudioVideoCompressionService $service;
    private string $sampleMediaPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->overrideStorageDriver('local');
        $this->service = new AudioVideoCompressionService();
        $this->sampleMediaPath = base_path('tests/Files/ffmpeg');
    }

    protected function tearDown(): void
    {
        // Clean up any test files created
        $this->cleanupTestFiles('video', ['test_720p.mp4','test_360p.mp4','test_720.mp4','test_to_720.mp4']);
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
        $this->assertVideoIs720pOrLess($destinationFilename);
    }

    public function test_it_compresses_2K_video_to_720p_landscape()
    {
        // Copy fixture to test location
        $sourceFilename = 'video_2K.mp4';
        $destinationFilename = 'test_to_720.mp4';
        $this->copySampleMediaToStorage($sourceFilename, 'video', $destinationFilename);

        $result = $this->service->compress('video', $destinationFilename, 'video');

        $this->assertTrue($result);
        $this->assertTrue(Storage::disk('video')->exists($destinationFilename));

        // Verify it's actually 720p now
        $this->assertVideoIs720pOrLess($destinationFilename);
    }

    public function test_it_compresses_4K_video_to_720p_landscape()
    {
        // Copy fixture to test location
        $sourceFilename = 'video_4K.mp4';
        $destinationFilename = 'test_to_720.mp4';
        $this->copySampleMediaToStorage($sourceFilename, 'video', $destinationFilename);

        $result = $this->service->compress('video', $destinationFilename, 'video');

        $this->assertTrue($result);
        $this->assertTrue(Storage::disk('video')->exists($destinationFilename));

        // Verify it's actually 720p now
        $this->assertVideoIs720pOrLess($destinationFilename);
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
        $this->assertVideoIs720pOrLess($destinationFilename);
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
        $this->assertAudioIsMono();
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
        $this->assertAudioIsMono();
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
            $this->fail("Fixture file not found: $sourcePath");
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
            $this->fail("Failed to write fixture to disk: $disk");
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

    private function assertVideoIs720pOrLess(string $filename): void
    {
        try {
            // Resolve the real filesystem path from the Laravel disk
            $fullPath = Storage::disk('video')->path($filename);

            if (!file_exists($fullPath)) {
                $this->fail("Video file not found at path: $fullPath");
            }

            // Bypass LaravelFFMpeg to avoid temp cache / stale probe issues
            // This avoids errors where width and height are not correctly read
            $ffmpeg = FFMpeg::create();
            $video = $ffmpeg
                ->open($fullPath)
                ->getStreams()
                ->videos()
                ->first();

            if (!$video) {
                $this->fail("No video stream found in file: $filename");
            }

            $width  = (int) $video->get('width');
            $height = (int) $video->get('height');

            $shortSide = min($width, $height);

            $this->assertLessThanOrEqual(
                720,
                $shortSide,
                "Video dimensions {$width}x$height (short side: {$shortSide}px) exceed the 720p limit."
            );
        } catch (Throwable $e) {
            $this->fail('Failed assertVideoIs720pOrLess: ' . $e->getMessage());
        }
    }


    private function assertAudioIsMono(): void
    {
        try {
            // Resolve the full filesystem path from the Laravel disk
            $fullPath = Storage::disk('audio')->path('test_stereo.mp4');

            if (!file_exists($fullPath)) {
                $this->fail("Audio file not found at path: $fullPath");
            }

            // Bypass LaravelFFMpeg facade to avoid stale temp copies
            // This avoids errors where channels are not correctly read
            $ffmpeg = FFMpeg::create();
            $streams = $ffmpeg
                ->open($fullPath)
                ->getStreams();

            // Find the audio stream
            $audioStream = null;
            foreach ($streams as $stream) {
                if ($stream->get('codec_type') === 'audio') {
                    $audioStream = $stream;
                    break;
                }
            }

            if (!$audioStream) {
                $this->fail("No audio stream found in file: test_stereo.mp4");
            }

            $channels = (int) $audioStream->get('channels');

            if ($channels === 0) {
                $this->fail('Could not read audio channel information from stream');
            }

            $this->assertSame(
                1,
                $channels,
                "Audio should be mono (1 channel), found $channels"
            );

        } catch (Throwable $e) {
            $this->fail('Failed assertAudioIsMono: ' . $e->getMessage());
        }
    }
}
