<?php

namespace ec5\Services;

use Exception;
use Illuminate\Support\Facades\Storage;
use Image;
use Log;

class PhotoSaverService
{
    /**
     * Save a photo to specific dimensions
     *
     * @param $projectRef
     * @param $image
     * @param $fileName
     * @param $driver
     * @param array $dimensions (width, height)
     * @param int $quality
     * @return bool
     */
    public static function saveImage($projectRef, $image, $fileName, $driver, array $dimensions = [], $quality = 50): bool
    {
        try {
            $imageRealPath = $image->getRealPath();
            $img = Image::make($imageRealPath);
            // Crop and resize image
            if (count($dimensions) > 0) {
                $width = $dimensions[0];
                $height = isset($dimensions[1]) ? $dimensions[1] : null;
                $img->fit($width, $height);
            }

            $img->encode('jpg', $quality);
            // Save new image over existing
            $img->save();
            // Destroy after use
            $img->destroy();
            // Store the file into storage location, using specified driver
            Storage::disk($driver)->put(
                $projectRef . '/' . $fileName,
                file_get_contents($imageRealPath)
            );
            return true;
        } catch (Exception $e) {
            Log::error('Cannot save image', ['exception' => $e]);
            return false;
        }
    }

    /**
     * Save a photo to specific dimensions
     *
     * @param $projectRef
     * @param $imagePath
     * @param $fileName
     * @param $driver
     * @param array $dimensions (width, height)
     * @param int $quality
     * @return bool
     */
    public static function storeImage($projectRef, $imagePath, $fileName, $driver, array $dimensions = [], $quality = 50): bool
    {
        try {
            $img = Image::make($imagePath);
            // Crop and resize image
            if (count($dimensions) > 0) {
                $width = $dimensions[0];
                $height = isset($dimensions[1]) ? $dimensions[1] : null;
                $img->fit($width, $height);
            }
            $img->encode('jpg', $quality);
            // Save new image over existing
            $img->save();
            // Destroy after use
            $img->destroy();
            // Store the file into storage location, using specified driver
            Storage::disk($driver)->put(
                $projectRef . '/' . $fileName,
                file_get_contents($imagePath)
            );

            return true;
        } catch (Exception $e) {
            Log::error('Cannot save image', ['exception' => $e]);
            return false;
        }
    }
}
