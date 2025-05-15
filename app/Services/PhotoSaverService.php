<?php

namespace ec5\Services;

use Illuminate\Support\Facades\Storage;
use Image;
use Intervention\Image\Drivers\Imagick\Encoders\JpegEncoder;
use Log;
use Throwable;

class PhotoSaverService
{
    /**
     * Save a photo to specific dimensions and store it in the storage location
     */
    public static function saveImage(string $projectRef, $image, $fileName, $driver, array $dimensions = [], int $quality = 50): bool
    {
        try {
            // Get the real path of the uploaded image
            $imageRealPath = $image->getRealPath();

            // Process the image (crop, resize, and encode)
            $encodedImage = self::processImage($imageRealPath, $dimensions, $quality);

            // Store the image into the storage location with the specified driver
            Storage::disk($driver)->put(
                $projectRef . '/' . $fileName,
                $encodedImage,
                [
                    'visibility' => 'public',
                    'directory_visibility' => 'public'
                ]
            );

            return true;
        } catch (Throwable $e) {
            // Log the exception in case of an error
            Log::error('Cannot save image', ['exception' => $e]);
            return false;
        }
    }

    /**
     * Save a photo from the given path to specific dimensions and store it
     *
     */
    public static function storeImage(string $projectRef, string $imagePath, string $fileName, $driver, array $dimensions = [], int $quality = 50): bool
    {
        try {
            // Process the image (crop, resize, and encode)
            $encodedImage = self::processImage($imagePath, $dimensions, $quality);

            // Store the image into the storage location with the specified driver
            Storage::disk($driver)->put(
                $projectRef . '/' . $fileName,
                $encodedImage,
                [
                    'visibility' => 'public',
                    'directory_visibility' => 'public'
                ]
            );

            return true;
        } catch (Throwable $e) {
            // Log the exception in case of an error
            Log::error('Cannot save image', ['exception' => $e]);
            return false;
        }
    }

    /**
     * Process the image: resize, crop, and encode it
     */
    private static function processImage(string $imagePath, array $dimensions = [], int $quality = 50): string
    {
        // Read the image from the given path
        $img = Image::read($imagePath);

        // Crop and resize image if dimensions are provided
        if (count($dimensions) > 0) {
            $width = $dimensions[0];
            $height = $dimensions[1] ?? null;
            $img->cover($width, $height);
        }

        // Encode the image as JPEG with the specified quality
        $encodedImage = $img->encode(new JpegEncoder($quality));

        // Destroy the image object after use to free up memory
        unset($img);

        return $encodedImage;
    }
}
