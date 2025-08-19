<?php

namespace ec5\Services\Project;

use ec5\Libraries\Utilities\Common;
use Intervention\Image\Drivers\Imagick\Encoders\JpegEncoder;
use Laravolt\Avatar\Facade as Avatar;
use Log;
use Storage;
use Throwable;

class ProjectAvatarService
{
    protected array $width;
    protected array $height;
    protected int $quality;
    protected string $filename;
    protected array $drivers;
    protected array $fontSize;

    /**
     * Initializes project avatar service configuration from application settings.
     *
     * Loads avatar dimensions, quality, filename, storage drivers, and font sizes from configuration.
     */
    public function __construct()
    {
        $this->width = config('epicollect.media.project_avatar.width');
        $this->height = config('epicollect.media.project_avatar.height');
        $this->quality = config('epicollect.media.project_avatar.quality');
        $this->filename = config('epicollect.media.project_avatar.filename');
        $this->drivers = config('epicollect.media.project_avatar.driver');
        $this->fontSize = config('epicollect.media.project_avatar.font_size');
    }

    /**
     * Generates and stores project avatar images using the configured storage driver.
     *
     * Selects the appropriate avatar generation method based on the default filesystem driver configuration. Supports both local and S3 storage. Returns false if the storage driver is unsupported or if avatar generation fails.
     *
     * @param string $projectRef Unique reference identifier for the project.
     * @param string $projectName Name of the project used for avatar generation.
     * @return bool True on successful avatar generation and storage, false otherwise.
     */
    public function generate(string $projectRef, string $projectName): bool
    {
        $driver = config('filesystems.default');

        if ($driver === 's3') {
            return $this->generateS3($projectRef, $projectName);
        }

        if ($driver === 'local') {
            return $this->generateLocal($projectRef, $projectName);
        }

        Log::error('Storage driver not supported', ['driver' => $driver]);
        return false;
    }

    /**
     * Generates and saves project avatar images locally for both thumbnail and mobile formats.
     *
     * Creates avatar images using the project name and stores them in project-specific directories on local storage disks for thumbnails and mobile logos. Returns true on success, or false if an error occurs.
     *
     * @param string $projectRef Unique reference identifier for the project.
     * @param string $projectName Name of the project to be used in the avatar image.
     * @return bool True if avatars are generated and saved successfully; false otherwise.
     */
    private function generateLocal(string $projectRef, string $projectName): bool
    {
        try {
            $disks = ['project_thumb'];

            foreach ($disks as $disk) {
                if (!Storage::disk($disk)->exists($projectRef)) {
                    Log::info("Creating directory $disk/$projectRef");

                    Storage::disk($disk)->makeDirectory($projectRef);

                    $fullPath = Storage::disk($disk)->path($projectRef);

                    if (is_dir($fullPath)) {
                        // Recursively set 755 permissions up to storage/app (fix Laravel default 700 on new folders)
                        Common::setPermissionsRecursiveUp($fullPath);
                    } else {
                        Log::error("Directory not found after creation: $fullPath");
                    }
                }
            }

            // Generate and save avatars

            // Thumb avatar
            $thumbAvatar = Avatar::create($projectName)
                ->setDimension($this->width['thumb'])
                ->setFontSize($this->fontSize['thumb'])
                ->getImageObject();

            $thumbImageData = $thumbAvatar->encode(new JpegEncoder(quality: $this->quality));
            Storage::disk('project_thumb')->put(
                $projectRef . '/' . $this->filename,
                $thumbImageData->toString(),
                [
                    'visibility' => 'public',
                    'directory_visibility' => 'public',
                ]
            );
            return true;
        } catch (Throwable $e) {
            Log::error('Error creating project avatar', ['exception' => $e]);
            return false;
        }
    }

    /**
     * Generates and uploads project avatar images to S3 storage.
     *
     * Creates thumbnail and mobile avatar images for the given project name, encodes them as JPEGs, and uploads them to their respective S3 storage disks. Returns true on success, or false if an error occurs.
     *
     * @param string $projectRef Unique reference identifier for the project.
     * @param string $projectName Name of the project used to generate the avatar.
     * @return bool True if avatars are successfully generated and uploaded; false otherwise.
     */
    private function generateS3(string $projectRef, string $projectName): bool
    {
        try {
            $imageThumb = Avatar::create($projectName)
                ->setDimension($this->width['thumb'])
                ->setFontSize($this->fontSize['thumb'])
                ->getImageObject();

            $imageThumbEncoded = $imageThumb->encode(new JpegEncoder(75));

            // Then upload using Storage:put()
            Storage::disk('project_thumb')->put($projectRef . '/' . $this->filename, (string) $imageThumbEncoded);

            return true;
        } catch (Throwable $e) {
            Log::error('Error creating and uploading project avatar', ['exception' => $e]);
            return false;
        }
    }
}
