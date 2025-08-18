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

class ToEntryThumbLocalMacro extends ServiceProvider
{
    /**
     * Registers the 'toEntryThumbLocal' macro to generate thumbnails from local storage at runtime.
     */
    public function boot(): void
    {
        Response::macro('toEntryThumbLocal', function ($projectRef, $filename) {
            $photoPlaceholderFilename = config('epicollect.media.photo_placeholder.filename');
            $photoNotSyncedFilename = config('epicollect.media.photo_not_synced_placeholder.filename');

            if (!empty($filename)) {
                try {
                    // Get original image path
                    $storagePathPrefix = config("filesystems.disks.entry_original.root").'/';
                    $originalFilepath = $storagePathPrefix . $projectRef . '/' . $filename;

                    // Check if original file exists
                    if (!File::exists($originalFilepath)) {
                        throw new FileNotFoundException("Original file does not exist at path: " . $originalFilepath);
                    }

                    // Create 100x100 thumbnail from original
                    $image = Image::read($originalFilepath);
                    $thumbnail = $image->cover(
                        config('epicollect.media.entry_thumb')[0],
                        config('epicollect.media.entry_thumb')[1]
                    );
                    $thumbnailData = $thumbnail->toJpeg(70);

                    return response($thumbnailData, 200, [
                        'Content-Type' => config('epicollect.media.content_type.photo')
                    ]);

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
                    Log::error('Cannot generate thumbnail', ['exception' => $e]);
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
