<?php

namespace ec5\Services\Media;

use Aws\S3\Exception\S3Exception;
use ec5\DTO\ProjectDTO;
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
     * @param ProjectDTO $project
     * @param mixed $file UploadedFile or array with 'path' key (for S3).
     * @param string $fileName Desired name of the saved file.
     * @param string $disk Storage disk name (e.g., 'local', 's3', 'photo', etc.).
     * @param bool $isS3 Whether the source file is stored in S3.
     * @return bool True if saved successfully, false on failure.
     */
    public static function saveFile(ProjectDTO $project, mixed $file, string $fileName, string $disk, bool $isS3 = false): bool
    {
        $fileSaved = false;
        $targetPath = $project->ref . '/' . $fileName;

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

                        $fileSaved = Storage::disk($disk)->put($targetPath, $stream, [
                            'visibility' => 'public',
                            'directory_visibility' => 'public'
                        ]);
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
                $stream = fopen($file->getRealPath(), 'rb');

                if (!Storage::disk($disk)->exists($project->ref)) {
                    Storage::disk($disk)->makeDirectory($project->ref);

                    $diskRoot = config('filesystems.disks.' . $disk . '.root').'/';

                    $newDirFullPath = $diskRoot . $project->ref;
                    Common::setPermissionsRecursiveUp($newDirFullPath);
                }

                $fileSaved = Storage::disk($disk)->put($targetPath, $stream, [
                    'visibility' => 'public',
                    'directory_visibility' => 'public'
                ]);
                fclose($stream);
            }

            if ($fileSaved) {
                $fileBytes = $isS3 ? Storage::disk('s3')->size($file['path']) : $file->getSize();
                //adjust total bytes
                ProjectStats::where('project_id', $project->getId())
                    ->first()
                    ->adjustTotalBytes($fileBytes);
            }

            return (bool) $fileSaved;
        } catch (Throwable $e) {
            Log::error('Failed to save file', [
                'exception' => $e,
                'projectRef' => $project->ref,
                'fileName' => $fileName,
                'disk' => $disk,
                'isS3' => $isS3,
            ]);
            return false;
        }
    }
}
