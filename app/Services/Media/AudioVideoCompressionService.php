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
                    // We remove ->setKiloBitrate() because CRF handles it
                    ->setAdditionalParameters([
                        '-crf', '23',          // Target quality (23 is standard, 20 is high quality)
                        '-maxrate', '4000k',   // The "Cap": Don't let it spike above 4Mbps
                        '-bufsize', '8000k',   // Usually 2x the maxrate
                        '-preset', 'veryfast',
                        '-movflags', 'faststart'
                    ]);
                // Width: Auto (divisible by 2), but don't exceed original width
                // Height: 720, but don't exceed original height
                $scaleFilter = "scale='min(iw,-2)':'min(ih,720)'";
                FFMpeg::fromDisk($disk)
                    ->open($path)
                    ->addFilter('-vf', $scaleFilter)
                    ->export()
                    ->toDisk($disk)
                    ->inFormat($format)
                    ->save($compressedPath);
            } elseif ($type === 'audio') {
                // Don't use Aac() format - it requires libfdk_aac
                // Instead, export without format and add codec params directly
                FFMpeg::fromDisk($disk)
                    ->open($path)
                    ->addFilter([
                        '-vn',
                        '-c:a', 'aac',
                        '-q:a', '3',
                        '-ar', '44100'
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
            if ($verificationResult === 'keep_original') {
                Log::error('Compression result larger than original - skipping swap', ['path' => $path]);
                Storage::disk($disk)->delete($compressedPath);
                $success = true; // Return true to stop retries, we are done.
            }

            if ($verificationResult === 'replace') {

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
            }
            if ($verificationResult === 'fail') {
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
    private function verifyCompressedFile(string $disk, string $originalPath, string $compressedPath): string
    {
        $diskInstance = Storage::disk($disk);

        if (!$diskInstance->exists($compressedPath) || $diskInstance->size($compressedPath) < 1000) {
            return 'fail';
        }

        if ($diskInstance->size($compressedPath) >= $diskInstance->size($originalPath)) {
            return 'keep_original';
        }

        return 'replace';
    }
}
