<?php

namespace ec5\Services\Media;

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

        //skip .wav files (do not compress them) due to legacy implementation.
        //on iOS we can only record .wav files using Cordova
        //iOS user base is non existent anyway
        if (str_ends_with($path, '.wav')) {
            return true;
        }

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
        //keep original extension (mp4 or wav for ios audio files)
        $compressedPath = preg_replace('/(\.\w+)$/', '_compressed$1', $path);
        $success = false;

        try {
            if ($type === 'video') {
                // Video format configuration
                $format = (new X264('aac'))
                    ->setKiloBitrate(800)     // video bitrate
                    ->setAudioKiloBitrate(96); // audio bitrate AAC
                FFMpeg::fromDisk($disk)
                    ->open($path)
                    ->addFilter('-vf', 'scale=-2:480') // width auto, divisible by 2; max height 480
                    ->export()
                    ->toDisk($disk)
                    ->inFormat($format)
                    ->save($compressedPath);
            } elseif ($type === 'audio') {
                // Don't use Aac() format - it requires libfdk_aac
                // Instead, export without format and add codec params directly
                FFMpeg::fromDisk($disk)
                    ->open($path)
                    ->addFilter(['-vn', '-c:a', 'aac', '-b:a', '128k', '-ar', '44100'])
                    ->export()
                    ->toDisk($disk)
                    ->save($compressedPath);
            } else {
                throw new InvalidArgumentException("Unsupported media type: $type");
            }

            // If FFmpeg succeeded without throwing, the file is valid and playable
            // Just verify it exists and has reasonable content
            sleep(1); // Let filesystem catch up

            if ($this->verifyCompressedFile($disk, $path, $compressedPath)) {
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

                // Replace original
                if (Storage::disk($disk)->move($compressedPath, $path)) {
                    $success = true;
                } else {
                    // Cleanup failed move
                    Log::error('Failed to move compressed file', ['path' => $path]);
                    Storage::disk($disk)->delete($compressedPath);
                }
            } else {
                // Cleanup failed compression
                Storage::disk($disk)->delete($compressedPath);
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
    private function verifyCompressedFile(string $disk, string $originalPath, string $compressedPath): bool
    {
        try {
            $diskInstance = Storage::disk($disk);

            if (!$diskInstance->exists($compressedPath)) {
                Log::warning('Compressed file does not exist', ['path' => $compressedPath]);
                return false;
            }

            $compressedSize = $diskInstance->size($compressedPath);

            // Sanity check: file must have content (minimum 1KB for valid media)
            if ($compressedSize < 1000) {
                Log::warning('Compressed file suspiciously small', [
                    'path' => $compressedPath,
                    'size' => $compressedSize
                ]);
                return false;
            }

            // Optional: verify compression actually reduced size
            $originalSize = $diskInstance->size($originalPath);
            if ($compressedSize >= $originalSize) {
                Log::info('Compressed file not smaller, keeping original', [
                    'path' => $compressedPath,
                    'original_size' => $originalSize,
                    'compressed_size' => $compressedSize
                ]);
                return false;
            }

            return true;

        } catch (Throwable $e) {
            Log::error('Failed to verify compressed file', [
                'compressedPath' => $compressedPath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
