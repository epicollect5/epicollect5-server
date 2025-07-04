<?php

namespace ec5\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Drivers\Imagick\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Laravel\Facades\Image;
use InvalidArgumentException;
use Log;
use RuntimeException;
use Throwable;

class PhotoSaverService
{
    /**
     * Saves an image to the configured storage driver, supporting both local and S3 storage.
     *
     * Determines the default storage driver from configuration and delegates the image saving process to the appropriate method. Returns false if the storage driver is unsupported.
     *
     * @param string $projectRef Reference identifier for the project.
     * @param mixed $image Uploaded file instance or file path to the image.
     * @param string $fileName Name to assign to the saved image file.
     * @param string $disk Storage disk to use for saving the image.
     * @param array $dimensions Optional width and height for resizing or cropping the image.
     * @param int $quality JPEG encoding quality (default 50).
     * @return bool True on success, false on failure or if the storage driver is unsupported.
     */
    public static function saveImage(string $projectRef, mixed $image, string $fileName, string $disk, array $dimensions = [], int $quality = 50): bool
    {
        $storageDriver = config('filesystems.default');
        if ($storageDriver === 's3') {
            return self::saveImageS3($projectRef, $image, $fileName, $disk, $dimensions, $quality);
        }
        if ($storageDriver === 'local') {
            return self::saveImageLocal($projectRef, $image, $fileName, $disk, $dimensions, $quality);
        }

        Log::error('Storage driver not supported', ['driver' => $storageDriver]);
        return false;
    }


    /**
     * Saves an image to local storage, optionally resizing and cropping it to specified dimensions and encoding it as JPEG.
     *
     * Accepts either an uploaded file or a file path as input. The processed image is stored on the specified disk with public visibility.
     *
     * @param string $projectRef Reference identifier for the project, used as a directory prefix.
     * @param mixed $image Uploaded file object or file path to the image.
     * @param string $fileName Name for the saved image file.
     * @param string $disk Storage disk name where the image will be saved.
     * @param array $dimensions Optional array with width and height for resizing and cropping.
     * @param int $quality JPEG encoding quality (1-100).
     * @return bool True on success, false if saving fails.
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
            Log::error('Cannot save image', ['exception' => $e]);
            return false;
        }
    }

    /**
     * Saves an image to an S3 storage disk after processing.
     *
     * Accepts either an uploaded file or a string path referencing an S3 object. The image is processed (optionally resized and cropped, then encoded as JPEG with the specified quality) and uploaded to the specified S3 disk under the given project reference and file name.
     *
     * @param string $projectRef Project reference used as the directory path in S3.
     * @param mixed $image Uploaded file or S3 object path to be processed and saved.
     * @param string $fileName Name for the saved image file.
     * @param string $disk S3 disk name where the image will be saved.
     * @param array $dimensions Optional width and height for resizing and cropping.
     * @param int $quality JPEG encoding quality (default 50).
     * @return bool True on success, false if saving fails.
     */
    public static function saveImageS3(
        string $projectRef,
        mixed $image,
        string $fileName,
        string $disk,
        array $dimensions = [],
        int $quality = 50
    ): bool {
        try {
            if ($image instanceof UploadedFile) {
                //Mobile uploads are UploadedFile instances
                $imageContent = self::processImage($image->getRealPath(), $dimensions, $quality);
            } elseif (is_string($image)) {
                $stream = Storage::disk('s3')->readStream($image);
                if (!$stream) {
                    throw new RuntimeException("Could not open stream from S3 path: $image");
                }

                $imageContent = self::processImageS3($stream, $dimensions, $quality);
                fclose($stream);
            } else {
                throw new InvalidArgumentException('Unsupported image type.');
            }

            // Upload processed image to S3
            Storage::disk($disk)->put(
                $projectRef . '/' . $fileName,
                $imageContent
            );

            return true;
        } catch (Throwable $e) {
            Log::error('Cannot save image to S3', ['exception' => $e]);
            return false;
        }
    }


    /**
     * Processes an image from a local path by optionally cropping and resizing it, then encoding it as a JPEG.
     *
     * @param string $imagePath Path to the source image file.
     * @param array $dimensions Optional array specifying [width, height] for cropping and resizing. If only width is provided, height defaults to width.
     * @param int $quality JPEG encoding quality (1-100).
     * @return string JPEG-encoded image data.
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

    /**
     * Processes an image from a stream, optionally resizing and cropping it, and encodes it as a JPEG.
     *
     * @param resource $stream Stream resource containing the image data.
     * @param array $dimensions Optional array with width and height for cropping and resizing. If only width is provided, height defaults to width.
     * @param int $quality JPEG encoding quality (default 50).
     * @return string JPEG-encoded image data.
     * @throws RuntimeException If the stream is invalid or cannot be opened.
     */
    private static function processImageS3($stream, array $dimensions = [], int $quality = 50): string
    {
        if (!$stream) {
            throw new RuntimeException("Could not open stream for S3 image processing");
        }

        if (!is_resource($stream)) {
            Log::error('Cannot process image from S3', ['exception' => 'Invalid stream resource']);
            throw new InvalidArgumentException("Expected stream resource, got " . gettype($stream));
        }

        $manager = new ImageManager(new Driver());
        $image = $manager->read($stream);

        // Apply resizing if needed
        if (!empty($dimensions)) {
            $width = $dimensions[0];
            $height = $dimensions[1] ?? $width;
            $image->cover($width, $height);
        }

        $encoded = $image->toJpeg($quality);

        unset($image);

        return (string) $encoded;
    }

}
