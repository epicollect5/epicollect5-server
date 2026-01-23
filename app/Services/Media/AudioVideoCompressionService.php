<?php

namespace ec5\Services\Media;

use Exception;
use FFMpeg;
use FFMpeg\Format\Video\X264;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

class AudioVideoCompressionService
{
    /**
     * Compress a video or audio file stored on the given Laravel disk.
     *
     * The service:
     * 1. Creates a compressed version (filename_compressed.*)
     * 2. Verifies that the output exists and has content
     * 3. Replaces the original file only if compression succeeded
     * 4. Retries up to 3 times on transient errors
     *
     * @param string $disk Storage disk name (e.g. 'video', 'audio', 'local')
     * @param string $path Path to the file within the disk
     * @param string $type Either 'video' or 'audio'
     * @return bool True if compression succeeded and original replaced
     */
    public function compress(string $disk, string $path, string $type): bool
    {
        $maxRetries = 3;
        $delay = 1; // seconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            Log::info("Compression attempt $attempt/$maxRetries", [
                'disk' => $disk,
                'path' => $path,
                'type' => $type,
            ]);

            try {
                if ($this->tryCompress($disk, $path, $type)) {
                    Log::info('Compression succeeded', ['path' => $path]);
                    return true;
                }

                // Wait before next retry
                sleep($delay);
                $delay *= 2;

            } catch (Throwable $e) {
                Log::warning('Compression attempt failed', [
                    'attempt' => $attempt,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);

                sleep($delay);
                $delay *= 2;
            }
        }

        Log::error('Compression failed after all retries', ['path' => $path]);
        return false;
    }

    /**
     * Single compression attempt. Returns true on verified success.
     */
    private function tryCompress(string $disk, string $path, string $type): bool
    {
        $dir = pathinfo($path, PATHINFO_DIRNAME);
        $base = pathinfo($path, PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $compressedFilename = $base . '_compressed.mp4';
        $compressedPath = ($dir && $dir !== '.') ? $dir . '/' . $compressedFilename : $compressedFilename;

        // LEGACY GUARD: Skip compression for .wav files and return true
        if ($extension === 'wav') {
            Log::info('Skipping: .wav file detected for legacy support', ['path' => $path]);
            return true;
        }

        $media = FFMpeg::fromDisk($disk)->open($path);
        // These methods are passed to the underlying driver via __call()
        $format  = $media()->getFormat();
        $bitrate = (int) $format->get('bit_rate');
        $success = false;

        try {
            if ($type === 'video') {

                // 1. Check Dimensions (using the package helper)
                $videoStream = $media->getVideoStream();
                $w = $videoStream->get('width');
                $h = $videoStream->get('height');

                // Find the "Short Side" (e.g., 720 in a 720x1280 portrait video)
                $shortSide = min($w, $h);

                // SKIP LOGIC:
                // If it's already 720p (or less)
                if ($shortSide <= 720) {
                    Log::info('Skipping: Video is already 720p', ['path' => $path]);
                    return true;
                }

                // Video format: Targets 720p and 30fps but keeps audio channels
                $format = (new X264('aac'))
                    ->setAudioKiloBitrate(128) // Higher bitrate for potential Stereo
                    ->setAdditionalParameters([
                        '-crf', '23',
                        '-maxrate', '2000k',
                        '-bufsize', '4000k',
                        '-r', '30',              // Force 30fps consistency
                        '-preset', 'veryfast',
                        '-movflags', 'faststart'
                    ]);

                // Logic: Shortest side max 720, maintain aspect ratio, no upscaling
                $scaleFilter = "scale='if(gt(iw,ih),-2,min(iw,720))':'if(gt(iw,ih),min(ih,720),-2)'";

                $media->addFilter(function ($filters) use ($scaleFilter) {
                    $filters->custom($scaleFilter);
                })
                    ->export()
                    ->toDisk($disk)
                    ->inFormat($format)
                    ->save($compressedPath);
            } elseif ($type === 'audio') {
                $audioStream = $media->getAudioStream();
                $channels = (int) $audioStream->get('channels');

                // SKIP: If already Mono and <= 70kbps (catches your old 96k/stereo uploads)
                if ($channels === 1 && $bitrate > 0 && $bitrate <= 70000) {
                    return true;
                }

                // Don't use Aac() format - it requires libfdk_aac
                // Instead, export without format and add codec params directly
                FFMpeg::fromDisk($disk)
                    ->open($path)
                    ->addFilter([
                        '-vn', //no video
                        '-c:a', 'aac', //codec
                        '-b:a', '64k', // Bitrate
                        '-ac', '1', // Channels (Mono)
                        '-ar', '44100', // Sample rate
                         '-f', 'mp4'// container
                    ])
                    ->export()
                    ->toDisk($disk)
                    ->save($compressedPath);
            } else {
                throw new InvalidArgumentException("Unsupported media type: $type");
            }

            // If FFmpeg succeeded without throwing, the file is valid and playable
            // Just verify it exists and has reasonable content
            sleep(1); // Let filesystem catch up

            $verificationResult = $this->verifyCompressedFile($disk, $path, $compressedPath);


            switch ($verificationResult) {
                case 'replace':
                    // Log compression stats
                    $diskInstance = Storage::disk($disk);
                    $originalSize = $diskInstance->size($path);
                    $compressedSize = $diskInstance->size($compressedPath);
                    $compressionRate = $originalSize > 0 ? ($originalSize - $compressedSize) / $originalSize * 100 : 0;

                    Log::info('Media compressed successfully', [
                        'path' => $path,
                        'type' => $type,
                        'original_size_mb' => round($originalSize / 1024 / 1024, 2),
                        'compressed_size_mb' => round($compressedSize / 1024 / 1024, 2),
                        'compression_rate_percent' => round($compressionRate, 2)
                    ]);

                    if (Storage::disk($disk)->move($compressedPath, $path)) {
                        $success = true;
                    } else {
                        // Cleanup failed move
                        Log::error('Failed to move compressed file', ['path' => $path]);
                        Storage::disk($disk)->delete($compressedPath);
                    }
                    break;
                case 'fail':
                    throw new Exception('Compression failed');
                default:
                    throw new Exception("Unknown verification result: $verificationResult");
            }
        } catch (Throwable $e) {
            Log::error('Compression error', [
                'path' => $path,
                'type' => $type,
                'exception' => $e->getMessage(),
            ]);
            // Ensure temp file removed
            if (Storage::disk($disk)->exists($compressedPath)) {
                Storage::disk($disk)->delete($compressedPath);
            }
        }

        return $success;
    }

    /**
     * Verify that the compressed file exists and has reasonable content.
     * If FFmpeg completed without errors, the file is playable - we just
     * need basic sanity checks here.
     */
    private function verifyCompressedFile(string $disk, string $originalPath, string $compressedPath): string
    {
        $diskInstance = Storage::disk($disk);

        if (!$diskInstance->exists($compressedPath) || $diskInstance->size($compressedPath) < 1000) {
            return 'fail';
        }
        return 'replace';
    }
}
