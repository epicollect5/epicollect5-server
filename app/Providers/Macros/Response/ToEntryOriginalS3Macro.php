<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class ToEntryOriginalS3Macro extends ServiceProvider
{
    /**
     * Registers the 'toEntryOriginalS3' macro to generate entry original from S3 storage at runtime.
     */
    public function boot(): void
    {
        Response::macro('toEntryOriginalS3', function ($projectRef, $filename) {
            $photoRendererService = app('ec5\Services\Media\PhotoRendererService');
            $disk = Storage::disk(config('epicollect.media.media_formats_disks.entry_original'));

            if (!$filename) {
                return $photoRendererService->placeholderOrFallback(null);
            }

            $path = $projectRef . '/' . $filename;

            $resolvedPath = $photoRendererService->resolvePhotoPath($disk, $path);
            if (!$resolvedPath) {
                return $photoRendererService->placeholderOrFallback($filename);
            }

            $imageContent = $photoRendererService->getAsJpeg(
                $disk,
                $resolvedPath,
                config('epicollect.media.quality.jpg')
            );

            sleep(config('epicollect.setup.api_sleep_time.media'));
            return response($imageContent, 200, [
                'Content-Type' => config('epicollect.media.content_type.photo')
            ]);
        });
    }
}
