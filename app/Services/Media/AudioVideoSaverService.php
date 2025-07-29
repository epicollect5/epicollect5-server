<?php

namespace ec5\Services\Media;

use ec5\Libraries\Utilities\Common;
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
     * @param string $projectRef Directory prefix to store the file under.
     * @param mixed $file UploadedFile or array with 'path' key (for S3).
     * @param string $fileName Desired name of the saved file.
     * @param string $disk Storage disk name (e.g., 'local', 's3', 'entry_original', etc.).
     * @param bool $isS3 Whether the source file is stored in S3.
     * @return bool True if saved successfully, false on failure.
     */
    public static function saveFile(string $projectRef, mixed $file, string $fileName, string $disk, bool $isS3 = false): bool
    {
        $targetPath = $projectRef . '/' . $fileName;

        try {
            if ($isS3) {
                $stream = Storage::disk('s3')->readStream($file['path'] ?? '');

                if (!$stream) {
                    Log::error('Failed to read stream from S3', ['file' => $file['path'] ?? '']);
                    return false;
                }

                $fileSaved = Storage::disk($disk)->put($targetPath, $stream, [
                    'visibility' => 'public',
                    'directory_visibility' => 'public'
                ]);
                fclose($stream);
            } else {
                $stream = fopen($file->getRealPath(), 'rb');

                if (!Storage::disk($disk)->exists($projectRef)) {
                    Storage::disk($disk)->makeDirectory($projectRef);

                    $diskRoot = Storage::disk($disk)->path('');
                    $newDirFullPath = $diskRoot . $projectRef;
                    Common::setPermissionsRecursiveUp($newDirFullPath);
                }

                $fileSaved = Storage::disk($disk)->put($targetPath, $stream, [
                    'visibility' => 'public',
                    'directory_visibility' => 'public'
                ]);
                fclose($stream);
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
