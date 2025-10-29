<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Throwable;

class ToEntryThumbS3Macro extends ServiceProvider
{
    /**
     * Registers the 'toEntryThumbS3' macro to generate thumbnails from S3 storage at runtime.
     */
    public function boot(): void
    {
        Response::macro('toEntryThumbS3', function ($projectRef, $filename) {
            $photoRendererService = app('ec5\Services\Media\PhotoRendererService');
            $photoPlaceholderFilename = config('epicollect.media.generic_placeholder.filename');
            $photoNotSyncedFilename = config('epicollect.media.photo_not_synced_placeholder.filename');

            if (!empty($filename)) {
                try {
                    // Get original image path from S3
                    $path = $projectRef . '/' . $filename;
                    $disk = Storage::disk('photo');

                    $resolvedPath = $photoRendererService->resolvePhotoPath($disk, $path);

                    if (!$resolvedPath) {
                        throw new FileNotFoundException("File not found on S3: $path");
                    }

                    $imageContent = $photoRendererService->getAsJpeg($disk, $resolvedPath);

                    $thumbnailData = $photoRendererService->createThumbnail(
                        $imageContent,
                        config('epicollect.media.entry_thumb')[0],
                        config('epicollect.media.entry_thumb')[1],
                        config('epicollect.media.quality.jpg')
                    );

                    return response(
                        $thumbnailData,
                        200,
                        ['Content-Type' => config('epicollect.media.content_type.photo')]
                    );

                } catch (FileNotFoundException) {
                    // Return appropriate placeholder
                    if ($filename !== config('epicollect.media.project_avatar.filename')) {
                        $file = Storage::disk('public')->get($photoNotSyncedFilename);
                    } else {
                        $file = Storage::disk('public')->get($photoPlaceholderFilename);
                    }
                    $response = Response::make($file);
                    $response->header('Content-Type', config('epicollect.media.content_type.photo'));
                    return $response;
                } catch (Throwable $e) {
                    Log::error('Cannot generate S3 thumbnail', ['exception' => $e]);
                }
            }

            // Default placeholder
            $file = Storage::disk('public')->get($photoPlaceholderFilename);
            $response = Response::make($file);
            $response->header('Content-Type', config('epicollect.media.content_type.photo'));
            return $response;
        });
    }
}
