<?php

namespace ec5\Services\Media;

use Aws\S3\Exception\S3Exception;
use ec5\Libraries\Utilities\Common;
use ec5\Models\Project\ProjectStats;
use Log;
use Storage;
use Throwable;

class AudioVideoSaverService
{
    /**
     * Save a non-photo file to the specified storage disk.
     *
     * Supports both local and S3 storage.
     *
     * @param string $projectRef
     * @param int $projectId
     * @param mixed $file UploadedFile or array with 'path' key (for S3).
     * @param string $fileName Desired name of the saved file.
     * @param string $disk Storage disk name (e.g., 'local', 's3', 'photo', etc.).
     * @param bool $isS3 Whether the source file is stored in S3.
     * @return bool True if saved successfully, false on failure.
     */
    public static function saveFile(string $projectRef, int $projectId, mixed $file, string $fileName, string $disk, bool $isS3 = false): bool
    {
        $fileSaved = false;
        $targetPath = $projectRef . '/' . $fileName;
        $photoBytes = 0;
        $audioBytes = 0;
        $videoBytes = 0;
        $photoFiles = 0;
        $audioFiles = 0;
        $videoFiles = 0;

        try {
            if ($isS3) {
                $stream = Storage::disk('s3')->readStream($file['path'] ?? '');

                if (!$stream) {
                    Log::error('Failed to read stream from S3', ['file' => $file['path'] ?? '']);
                    return false;
                }

                // Upload to S3 with retries
                $maxRetries = 3;
                $retryDelay = 1; // seconds

                for ($retry = 0; $retry <= $maxRetries; $retry++) {
                    try {
                        // Rewind stream before each attempt (including first)
                        if (is_resource($stream)) {
                            $meta = stream_get_meta_data($stream);
                            if (!empty($meta['seekable'])) {
                                rewind($stream);
                            } else {
                                // Stream is not seekable, reopen it
                                fclose($stream);
                                $stream = Storage::disk('s3')->readStream($file['path'] ?? '');
                                if (!$stream) {
                                    Log::error('Failed to reopen stream from S3', ['file' => $file['path'] ?? '']);
                                    return false;
                                }
                            }
                        }

                        $fileSaved = Storage::disk($disk)->put($targetPath, $stream);
                        if ($fileSaved) {
                            break; // Success, exit retry loop
                        }
                    } catch (Throwable $e) {
                        if ($retry === $maxRetries || !($e instanceof S3Exception && Common::isRetryableError($e))) {
                            fclose($stream);
                            throw $e;
                        }
                        sleep($retryDelay * pow(2, $retry));
                    }
                }
                fclose($stream);
            } else {
                // Open the uploaded file as a read-only binary stream
                // We use a stream instead of reading the whole file into memory because
                // audio and video files can be very large. Streaming allows Laravel
                // to copy the file in chunks, avoiding high memory usage.
                $stream = fopen($file->getRealPath(), 'rb');

                // Ensure the target project directory exists
                if (!Storage::disk($disk)->exists($projectRef)) {
                    Storage::disk($disk)->makeDirectory($projectRef);

                    $diskRoot = config('filesystems.disks.' . $disk . '.root') . '/';

                    $newDirFullPath = $diskRoot . $projectRef;

                    // Recursively set folder permissions up to the disk root
                    // (skipped in testing to avoid errors)
                    Common::setPermissionsRecursiveUp($newDirFullPath);
                }

                // Store the file on the specified disk using the stream
                // This is efficient for large files (audio/video) because it
                // avoids loading the entire file into PHP memory at once
                $fileSaved = Storage::disk($disk)->put($targetPath, $stream);

                // Close the stream to free resources
                fclose($stream);
            }

            if ($fileSaved) {
                //compress the file if it is a video
                if ($disk === 'video' || $disk === 'audio') {
                    $compressionService = app(AudioVideoCompressionService::class);
                    $compressionSuccess = $compressionService->compress($disk, $targetPath, $disk);

                    if (!$compressionSuccess) {
                        Log::warning("Compression failed for $targetPath");
                        // Optionally return false to force user re-upload
                    }
                }

                // Get actual file size after potential compression
                $fileBytes = Storage::disk($disk)->size($targetPath);
                match($disk) {
                    'photo' => $photoBytes = $fileBytes,
                    'audio' => $audioBytes = $fileBytes,
                    'video' => $videoBytes = $fileBytes
                };
                match($disk) {
                    'photo' => $photoFiles = 1,
                    'audio' => $audioFiles = 1,
                    'video' => $videoFiles = 1
                };
                //adjust total bytes
                ProjectStats::where('project_id', $projectId)
                    ->first()
                    ->incrementMediaStorageUsage(
                        $photoBytes,
                        $photoFiles,
                        $audioBytes,
                        $audioFiles,
                        $videoBytes,
                        $videoFiles
                    );
            }

            return (bool) $fileSaved;
        } catch (Throwable $e) {
            Log::error('Failed to save file', [
                'exception' => $e,
                'projectRef' => $projectRef,
                'fileName' => $fileName,
                'disk' => $disk,
                'isS3' => $isS3,
            ]);
            return false;
        }
    }
}
