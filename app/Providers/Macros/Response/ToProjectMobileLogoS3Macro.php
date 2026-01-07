<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Laravel\Facades\Image;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Throwable;

class ToProjectMobileLogoS3Macro extends ServiceProvider
{
    /**
     * Registers the 'ToProjectMobileLogoS3' macro
     * to generate project mobile logos
     * from S3 storage at runtime.
     */
    public function boot(): void
    {
        Response::macro('toProjectMobileLogoS3', function ($projectRef, $filename) {
            $disk = Storage::disk('project');
            $photoPlaceholderFilename = config('epicollect.media.generic_placeholder.filename');
            [$w, $h] = config('epicollect.media.project_mobile_logo');

            if (!empty($filename)) {
                try {
                    // Get original image path from S3
                    $path = $projectRef . '/' . $filename;

                    if (!$disk->exists($path)) {
                        throw new FileNotFoundException("Project mobile logo file not found on S3: $path");
                    }

                    // Read image from S3 and create project mobile logo
                    $stream = null;
                    try {
                        $stream = $disk->readStream($path);
                        $image = Image::read($stream);
                    } finally {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }

                    $thumbnail = $image->cover(
                        $w,
                        $h
                    );
                    $thumbnailData = $thumbnail->toJpeg(70);

                    return response($thumbnailData, 200, [
                        'Content-Type' => config('epicollect.media.content_type.photo')
                    ]);

                } catch (FileNotFoundException) {
                    //ignore as legacy projects might not have a logo
                } catch (Throwable $e) {
                    Log::error('Cannot generate S3 project mobile logo', ['exception' => $e]);
                }
            }

            // Default placeholder
            $file = Storage::disk('public')->get($photoPlaceholderFilename);
            // Read bytes and create project mobile logo
            $image = Image::read($file);
            $thumbnail = $image->cover(
                $w,
                $h
            );
            $thumbnailData = $thumbnail->toJpeg(70);

            return response($thumbnailData, 200, [
                'Content-Type' => config('epicollect.media.content_type.photo')
            ]);
        });
    }
}
