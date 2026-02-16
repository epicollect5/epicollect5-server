<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Throwable;

class ToEntryOriginalLocalMacro extends ServiceProvider
{
    /**
     * Registers the 'toEntryOriginalLocal' macro to generate entry original from local storage at runtime.
     */
    public function boot(): void
    {
        Response::macro('toEntryOriginalLocal', function ($projectRef, $filename) {
            $photoRendererService = app('ec5\Services\Media\PhotoRendererService');
            $disk = Storage::disk(config('epicollect.media.media_formats_disks.entry_original'));

            if (!$filename) {
                return $photoRendererService->placeholderOrFallback(null);
            }

            try {
                $pathInDisk = $projectRef . '/' . $filename;
                $resolvedPath = $photoRendererService->resolvePhotoPath($disk, $pathInDisk);

                if (!$resolvedPath) {
                    return $photoRendererService->placeholderOrFallback($filename);
                }

                $imageContent = $photoRendererService->getAsJpeg(
                    $disk,
                    $resolvedPath,
                    config('epicollect.media.quality.jpg')
                );

                return response($imageContent, 200, [
                    'Content-Type' => config('epicollect.media.content_type.photo')
                ]);

            } catch (Throwable $e) {
                return $photoRendererService->placeholderOrFallback(null);
            }
        });
    }
}
