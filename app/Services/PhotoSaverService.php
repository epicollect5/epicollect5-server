<?php

namespace ec5\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;
use Log;
use Throwable;

class PhotoSaverService
{
    public static function saveImage(string $projectRef, mixed $image, string $fileName, string $disk, array $dimensions = [], int $quality = 50): bool
    {
        $storageDriver = config('filesystems.default');
        if ($storageDriver === 's3') {
            return self::saveImageS3($projectRef, $image, $fileName, $disk, $dimensions, $quality);
        } else {
            return self::saveImageLocal($projectRef, $image, $fileName, $disk, $dimensions, $quality);
        }
    }


    /**
     * Save a photo to specific dimensions and store it in the storage location
     *
     * @param string $projectRef Project reference identifier
     * @param mixed $image Image data (file or path)
     * @param string $fileName Target filename
     * @param string $disk Storage driver
     * @param array $dimensions Optional dimensions [width, height]
     * @param int $quality JPEG quality (1-100)
     * @return bool Success status
     */
    public static function saveImageLocal(string $projectRef, mixed $image, string $fileName, string $disk, array $dimensions = [], int $quality = 50): bool
    {
        try {
            // Get the image path (handles both uploaded files and direct paths)
            $imagePath = is_string($image) ? $image : $image->getRealPath();

            // Process the image (crop, resize, and encode)
            $encodedImage = self::processImage($imagePath, $dimensions, $quality);

            // Store the image into the storage location with the specified driver
            Storage::disk($disk)->put(
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

    public static function saveImageS3(string $projectRef, mixed $image, string $fileName, string $disk, array $dimensions = [], int $quality = 50): bool
    {
        try {
            // Get the image path (handles both uploaded files and direct paths)
            $imagePath = is_string($image) ? $image : $image->getRealPath();
            // Temporarily process the image in memory
            $processedImage = self::processImage($imagePath, $dimensions, $quality);

            // Upload to S3
            Storage::disk($disk)->put(
                $projectRef . '/' . $fileName,
                $processedImage,
                [
                    'visibility' => 'private',
                    'directory_visibility' => 'private'
                ]
            );

            return true;
        } catch (Throwable $e) {
            Log::error('Cannot save image to S3', ['exception' => $e]);
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
