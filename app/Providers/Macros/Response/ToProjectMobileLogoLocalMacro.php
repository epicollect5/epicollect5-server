<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Laravel\Facades\Image;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use File;
use Throwable;

class ToProjectMobileLogoLocalMacro extends ServiceProvider
{
    /**
     * Registers the 'ToProjectMobileLogoLocal' macro
     * to generate project mobile logos
     * from local storage at runtime.
     */
    public function boot(): void
    {
        Response::macro('toProjectMobileLogoLocal', function ($projectRef, $filename) {
            $photoPlaceholderFilename = config('epicollect.media.generic_placeholder.filename');

            if (!empty($filename)) {
                try {
                    // Get original image path
                    $storagePathPrefix = config("filesystems.disks.project.root").'/';
                    $originalFilepath = $storagePathPrefix . $projectRef . '/' . $filename;

                    // Check if original file exists
                    if (!File::exists($originalFilepath)) {
                        throw new FileNotFoundException("Original file does not exist at path: " . $originalFilepath);
                    }

                    // Create mobile logo from original
                    $image = Image::read($originalFilepath);
                    $thumbnail = $image->cover(
                        config('epicollect.media.project_mobile_logo')[0],
                        config('epicollect.media.project_mobile_logo')[1]
                    );
                    $thumbnailData = $thumbnail->toJpeg(70);

                    return response($thumbnailData, 200, [
                        'Content-Type' => config('epicollect.media.content_type.photo')
                    ]);

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
                config('epicollect.media.project_mobile_logo')[0],
                config('epicollect.media.project_mobile_logo')[1]
            );
            $resizedData = $resizedPlaceholder->toJpeg(70);

            return response($resizedData, 200, [
                'Content-Type' => config('epicollect.media.content_type.photo')
            ]);
        });
    }
}
