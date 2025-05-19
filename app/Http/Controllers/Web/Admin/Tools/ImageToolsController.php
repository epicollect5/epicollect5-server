<?php

namespace ec5\Http\Controllers\Web\Admin\Tools;

use ec5\Http\Controllers\Controller;
use ec5\Libraries\DirectoryGenerator\DirectoryGenerator;
use ec5\Services\PhotoSaverService;
use Storage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageToolsController extends Controller
{
    use DirectoryGenerator;

    /*
    |--------------------------------------------------------------------------
    | ImageToolsController Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles image manipulations
    |
    */

    /**
     * Resize any entry images that have the incorrect entry_original size
     * to whatever dimensions are in the media config file
     */
    public function resizeEntryImages()
    {
        $rootDisk = Storage::disk('entry_original');

        // Get directories for this driver
        $directories = $this->directoryGenerator($rootDisk);

        $i = 1;

        foreach ($directories as $directory) {

            // Get file paths in this directory
            $filePaths = $this->fileGenerator($rootDisk, $directory);

            foreach ($filePaths as $filePath) {

                $resize = false;

                // Get original file name (stripping the directory from it)
                $fileName = str_replace($directory . '/', '', $filePath);

                // Only want jpg files here
                $parts = pathinfo($fileName);
                if ($parts['extension'] != 'jpg') {
                    continue;
                }

                // Get the file source path
                $fileSourcePath = $rootDisk->path('') . $filePath;

                // Get the image width/height
                list($width, $height) = getimagesize($fileSourcePath);

                // Default dimensions to landscape
                $dimensions = config('epicollect.media.entry_original_landscape');

                // Check if it's landscape
                if ($width > $height) {

                    // If any of the current dimensions are off, image needs to be resized
                    if ($height != config('epicollect.media.entry_min') || $width != config('epicollect.media.entry_max')) {
                        // Dimensions already set to landscape
                        // Resize this image
                        $resize = true;
                    }
                } else {
                    // Otherwise it's portrait (or square)

                    // If any of the current dimensions are off, image needs to be resized
                    if ($height != config('epicollect.media.entry_max') || $width != config('epicollect.media.entry_min')) {
                        // Set portrait dimensions
                        $dimensions = config('epicollect.media.entry_original_portrait');
                        // Resize this image
                        $resize = true;
                    }
                }

                // Only attempt to resize if current image dimensions are incorrect
                if ($resize) {

                    // Attempt to save the new image
                    $thumb = PhotoSaverService::saveImage($directory, $fileSourcePath, $fileName, 'entry_original', $dimensions, 100);

                    // Check if any errors creating/saving thumb
                    if (!$thumb) {
                        echo 'Error with ' . $filePath;
                    } else {
                        echo $i . ' - File: ' . $filePath . '<br>' . 'Original Dimensions: ' . $width . ',' . $height . '<br>' . 'New Dimensions: ' . implode(', ', $dimensions) . '<br><br>';
                        $i++;
                    }
                }
            }
        }

        dd('Done!');

    }

    /**
     * Create the extra images needed for entries
     * First need to copy the entry_original image to the destination directory
     * Then resize that image to required dimensions
     */
    public function createEntryExtraImages()
    {
        $rootDisk = Storage::disk('entry_original');

        // Array of drivers we're going to copy and resize the original entry image to
        $drivers = [
            'entry_thumb'
        ];

        // Get directories for this driver
        $directories = $this->directoryGenerator($rootDisk);

        $i = 1;

        // Loop through all the entry original directories
        foreach ($directories as $directory) {

            // Get file paths in this directory
            $filePaths = $this->fileGenerator($rootDisk, $directory);

            foreach ($filePaths as $filePath) {

                // Get original file name (stripping the directory from it)
                $fileName = str_replace($directory . '/', '', $filePath);

                // Only want jpg files here
                $parts = pathinfo($fileName);
                if ($parts['extension'] != 'jpg') {
                    continue;
                }

                foreach ($drivers as $driver) {

                    $disk = Storage::disk($driver);

                    // Get the file source path
                    $fileSourcePath = $rootDisk->path('') . $filePath;

                    // Get the file destination path
                    $fileDestPath = $disk->path('') . $filePath;

                    // Get original image
                    $imageOriginal = new UploadedFile($fileSourcePath, $fileName);

                    // Copy the original image into the $filePath folder
                    $fileSaved = $disk->put(
                        $filePath,
                        file_get_contents($imageOriginal),
                        [
                            'visibility' => 'public',
                            'directory_visibility' => 'public'
                        ]
                    );

                    // If it successfully saves, resize it
                    if ($fileSaved) {

                        // Get the default dimensions (based on the driver)
                        $dimensions = config('epicollect.media.' . $driver);

                        // Attempt to resize and save the new image
                        $resizedImage = PhotoSaverService::saveImage($directory, $fileDestPath, $fileName, $driver, $dimensions);

                        // Check if any errors creating/saving
                        if (!$resizedImage) {
                            echo 'Error with ' . $filePath;
                        } else {
                            echo $i . ' - File: ' . $filePath . '<br>' . 'New Dimensions: ' . implode(', ', $dimensions) . '<br><br>';
                            $i++;
                        }
                    }

                }
            }
        }

        dd('Done!');

    }

}
