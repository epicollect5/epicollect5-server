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
     *
     * @param string $projectRef Project reference identifier
     * @param mixed $image Image data (file or path)
     * @param string $fileName Target filename
     * @param string $driver Storage driver
     * @param array $dimensions Optional dimensions [width, height]
     * @param int $quality JPEG quality (1-100)
     * @return bool Success status
     */
    public static function saveImage(string $projectRef, mixed $image, string $fileName, string $driver, array $dimensions = [], int $quality = 50): bool
    {
        try {
            // Get the image path (handles both uploaded files and direct paths)
            $imagePath = is_string($image) ? $image : $image->getRealPath();

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
     *
     * @param string $imagePath Path to the image
     * @param array $dimensions Optional dimensions [width, height]
     * @param int $quality JPEG quality (1-100)
     * @return string Encoded image data
     */
    private static function processImage(string $imagePath, array $dimensions = [], int $quality = 50): string
    {
        // Read the image from the given path
        $img = Image::read($imagePath);

        // Crop and resize image if dimensions are provided
        if (!empty($dimensions)) {
            $width = $dimensions[0];
            $height = $dimensions[1] ?? $width;
            $img->cover($width, $height);
        }

        // Encode the image as JPEG with the specified quality
        $encodedImage = $img->encode(new JpegEncoder($quality));

        // Destroy the image object after use to free up memory
        unset($img);

        return (string)$encodedImage;
    }
}
