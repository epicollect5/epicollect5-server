<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Throwable;

class ToProjectThumbS3Macro extends ServiceProvider
{
    /**
     * Registers the 'ToProjectMobileLogoS3' macro
     * to generate project mobile logos
     * from S3 storage at runtime.
     */
    public function boot(): void
    {
        Response::macro('toProjectThumbS3', function ($projectRef, $filename) {
            $photoRendererService = app('ec5\Services\Media\PhotoRendererService');
            $disk = Storage::disk('project');
            $photoPlaceholderFilename = config('epicollect.media.generic_placeholder.filename');

            if (!empty($filename)) {
                try {
                    // Get original image path from S3
                    $path = $projectRef . '/' . $filename;

                    $resolvedPath = $photoRendererService->resolvePhotoPath($disk, $path);

                    if (!$resolvedPath) {
                        throw new FileNotFoundException("File not found on S3: $path");
                    }

                    $imageContent = $photoRendererService->getAsJpeg($disk, $resolvedPath);

                    $thumbnailData = $photoRendererService->createThumbnail(
                        $imageContent,
                        config('epicollect.media.project_thumb')[0],
                        config('epicollect.media.project_thumb')[1],
                        config('epicollect.media.quality.jpg')
                    );

                    return response($thumbnailData, 200, [
                        'Content-Type' => config('epicollect.media.content_type.photo')
                    ]);

                } catch (FileNotFoundException $e) {
                    Log::error('Cannot find S3 project mobile logo', ['exception' => $e]);

                } catch (Throwable $e) {
                    Log::error('Cannot generate S3 project mobile logo', ['exception' => $e]);
                }
            }

            // Default placeholder
            $file = Storage::disk('public')->get($photoPlaceholderFilename);
            return response($file, 200, [
                'Content-Type' => config('epicollect.media.content_type.photo')
            ]);
        });
    }
}
