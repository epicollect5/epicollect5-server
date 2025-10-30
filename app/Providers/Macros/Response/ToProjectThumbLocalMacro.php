<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Laravel\Facades\Image;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Throwable;

class ToProjectThumbLocalMacro extends ServiceProvider
{
    /**
     * Registers the 'ToProjectMobileLogoLocal' macro
     * to generate project mobile logos
     * from local storage at runtime.
     */
    public function boot(): void
    {
        Response::macro('toProjectThumbLocal', function ($projectRef, $filename) {
            $photoRendererService = app('ec5\Services\Media\PhotoRendererService');
            $photoPlaceholderFilename = config('epicollect.media.generic_placeholder.filename');

            if (!empty($filename)) {
                try {
                    // Get original image path
                    $storagePathPrefix = config("filesystems.disks.project.root").'/';
                    $originalFilepath = $storagePathPrefix . $projectRef . '/' . $filename;

                    $resolvedPath = $photoRendererService->resolvePhotoPath(Storage::disk('project'), $projectRef . '/' . $filename);

                    if (!$resolvedPath) {
                        throw new FileNotFoundException("File not found on S3: $originalFilepath");
                    }

                    $imageContent = $photoRendererService->getAsJpeg(Storage::disk('project'), $resolvedPath);

                    // Create 100x100 thumbnail from original
                    $thumbnailData = $photoRendererService->createThumbnail(
                        $imageContent,
                        config('epicollect.media.project_thumb')[0],
                        config('epicollect.media.project_thumb')[1],
                        config('epicollect.media.quality.jpg')
                    );

                    return response(
                        $thumbnailData,
                        200,
                        ['Content-Type' => config('epicollect.media.content_type.photo')]
                    );

                } catch (FileNotFoundException $e) {
                    Log::error('Cannot find project mobile logo', ['exception' => $e]);

                } catch (Throwable $e) {
                    Log::error('Cannot generate project mobile logo', ['exception' => $e]);
                }
            }

            // Default placeholder - resize to mobile logo dimensions
            $file = Storage::disk('public')->get($photoPlaceholderFilename);
            $image = Image::read($file);
            $resizedPlaceholder = $image->cover(
                config('epicollect.media.project_thumb')[0],
                config('epicollect.media.project_thumb')[1]
            );
            $resizedData = $resizedPlaceholder->toJpeg(config('epicollect.media.quality.jpg'));

            return response($resizedData, 200, [
                'Content-Type' => config('epicollect.media.content_type.photo')
            ]);
        });
    }
}
