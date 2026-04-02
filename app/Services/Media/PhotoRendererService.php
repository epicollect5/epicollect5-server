<?php

namespace ec5\Services\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;

class PhotoRendererService
{
    private const string WEBP_EXTENSION = 'webp';

    /**
     * Resolve image path, checking for JPEG first, then WebP alternative
     */
    public function resolvePhotoPath(Filesystem $disk, string $path): ?string
    {
        // Check if requested file exists (JPEG/JPG)
        if ($disk->exists($path)) {
            return $path;
        }

        // Check if webp alternative exists
        $baseName = pathinfo($path, PATHINFO_FILENAME);
        $directory = pathinfo($path, PATHINFO_DIRNAME);
        $webpPath = ($directory !== '.' ? $directory . '/' : '') . $baseName . '.'.self::WEBP_EXTENSION;

        if ($disk->exists($webpPath)) {
            return $webpPath;
        }

        return null;
    }

    /**
     * Read and convert image to JPEG if needed
     */
    public function getAsJpeg(Filesystem $disk, string $resolvedPath, int $quality = 90): string
    {
        $extension = strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION));

        if ($extension === self::WEBP_EXTENSION) {
            return $this->convertWebpToJpeg($disk, $resolvedPath, $quality);
        }

        // Already JPEG, return as-is
        return $this->readFile($disk, $resolvedPath);
    }

    /**
     * Convert WebP to JPEG
     */
    protected function convertWebpToJpeg(Filesystem $disk, string $path, int $quality): string
    {
        $stream = $disk->readStream($path);
        $webpContent = stream_get_contents($stream);
        fclose($stream);

        $img = Image::read($webpContent);
        $jpegContent = $img->encode(new JpegEncoder(
            quality: $quality,
            strip: false
        ));

        unset($img, $webpContent);

        return (string) $jpegContent;
    }

    /**
     * Read file content from disk
     */
    protected function readFile(Filesystem $disk, string $path): string
    {
        $stream = $disk->readStream($path);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    /**
     * Create thumbnail from image content
     */
    public function createThumbnail(string $imageContent, int $width, int $height, int $quality = 70): string
    {
        $image = Image::read($imageContent);
        $thumbnail = $image->cover($width, $height);
        $thumbnailData = $thumbnail->toJpeg($quality);

        unset($image, $thumbnail);

        return (string) $thumbnailData;
    }
    public function placeholderOrFallback(?string $name = null): mixed
    {
        $genericPlaceholderFilename   = config('epicollect.media.generic_placeholder.filename');
        $photoNotSyncedFilename     = config('epicollect.media.photo_not_synced_placeholder.filename');
        $projectAvatarFilename      = config('epicollect.media.project_avatar.filename');
        $legacyProjectAvatarFilename      = config('epicollect.media.project_avatar.legacy_filename');
        $contentType                = config('epicollect.media.content_type.photo');

        // If no name provided → always return the generic placeholder
        if (is_null($name)) {
            $file = Storage::disk('public')->get($genericPlaceholderFilename);
        } else {
            // If it's NOT the project avatar, return "not synced" placeholder
            if ($name !== $projectAvatarFilename && $name !== $legacyProjectAvatarFilename) {
                $file = Storage::disk('public')->get($photoNotSyncedFilename);
            } else {
                // Special case: project avatar → normal placeholder
                $file = Storage::disk('public')->get($genericPlaceholderFilename);
            }
        }

        return Response::make($file, 200, ['Content-Type' => $contentType]);
    }
}
