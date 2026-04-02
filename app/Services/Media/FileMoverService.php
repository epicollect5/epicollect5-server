<?php

namespace ec5\Services\Media;

use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;

class FileMoverService
{
    /**
     * Stores an uploaded media file (photo, audio, or video) for a project entry, handling resizing  for photos.
     *
     * For photo files, determines orientation, resizes if necessary, and saves the original image using the appropriate storage (local or S3). For audio and video files, saves the file to the designated storage disk with public visibility. Sets error codes if file reading or saving fails.
     */
    public function moveFile(ProjectDTO $project, EntryStructureDTO $entryStructure): bool
    {
        // Get the entry data
        $fileEntry = $entryStructure->getEntry();
        $fileType = $fileEntry['type'];
        $fileName = $fileEntry['name'];
        $file = $entryStructure->getFile();

        $isS3 = is_array($file) && ($file['disk'] ?? '') === 's3';

        // Process each file type
        switch ($fileType) {

            case config('epicollect.strings.inputs_type.photo'):

                // === Get image dimensions ===
                $manager = new ImageManager(new Driver());

                if ($isS3) {
                    $stream = Storage::disk('s3')->readStream($file['path']);

                    if (!$stream) {
                        return false;
                    }

                    $image = $manager->read($stream);
                    fclose($stream);
                } else {
                    $image = $manager->read($file->getRealPath());
                }

                $width = $image->width();
                $height = $image->height();

                // === Choose dimensions ===
                $dimensions = ($width > $height)
                    ? config('epicollect.media.entry_original_landscape')
                    : config('epicollect.media.entry_original_portrait');

                // Skip resizing if dimensions match
                if ($width === $dimensions[0] && $height === $dimensions[1]) {
                    $dimensions = [];
                }

                // === Save original image ===
                $photoSaved = PhotoSaverService::saveImage(
                    $project->ref,
                    $project->getId(),
                    $isS3 ? $file['path'] : $file,
                    $fileName,
                    'photo',
                    $dimensions,
                    config('epicollect.media.quality.webp')
                );

                if (!$photoSaved) {
                    return false;
                }
                break;
            default:
                // === Save non-photo files ===
                $fileSaved = AudioVideoSaverService::saveFile(
                    $project->ref,
                    $project->getId(),
                    $file,
                    $fileName,
                    $fileType,// -> audio,video
                    $isS3
                );

                if (!$fileSaved) {
                    return false;
                }
                break;
        }
        return true;
    }
}
