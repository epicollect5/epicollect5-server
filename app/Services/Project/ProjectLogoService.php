<?php

namespace ec5\Services\Project;

use ec5\Libraries\Utilities\Common;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

class ProjectLogoService
{
    /**
     * Read the project logo from storage, resize to the given dimensions,
     * encode as WebP, and return a data URI.
     *
     * Returns null when the project ref is empty, no logo file exists on disk,
     * or the image cannot be processed.
     *
     * This method is stateless and does NOT touch the Laravel cache.
     * Callers are responsible for caching as appropriate.
     */
    public function generate(
        string $projectRef,
        int $width = 64,
        int $height = 64
    ): ?string {
        if (empty($projectRef)) {
            return null;
        }

        try {
            $disk = Storage::disk(Common::resolveDisk('project_thumb'));
            $logoPath = $projectRef . '/logo.jpg';

            if (!$disk->exists($logoPath)) {
                return null;
            }

            $stream = $disk->readStream($logoPath);
            if (!$stream) {
                return null;
            }

            try {
                $image = Image::read($stream);
                $image->cover($width, $height);
                $webp = $image->toWebp(50);
            } catch (Throwable $e) {
                Log::warning(
                    'ProjectLogoService: failed to process logo',
                    [
                        'project_ref' => $projectRef,
                        'exception' => $e->getMessage(),
                    ]
                );
                return null;
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            return 'data:image/webp;base64,' . base64_encode((string) $webp);
        } catch (Throwable $e) {
            Log::warning(
                'ProjectLogoService: unexpected error',
                [
                    'project_ref' => $projectRef,
                    'exception' => $e->getMessage(),
                ]
            );
            return null;
        }
    }
}
