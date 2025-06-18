<?php

namespace ec5\Services\Project;

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
    public function generate($projectRef, $projectName): bool
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
    protected function generateLocal($projectRef, $projectName): bool
    {
        try {
            //get thumb and mobile path
            $thumbPathPrefix = Storage::disk('project_thumb')->path('');
            $mobilePathPrefix = Storage::disk('project_mobile_logo')->path('');
            //create folder for this project ref
            Storage::disk($this->drivers['project_thumb'])->makeDirectory($projectRef);
            Storage::disk($this->drivers['project_mobile_logo'])->makeDirectory($projectRef);

            //generate thumb avatar
            Avatar::create($projectName)
                ->setDimension($this->width['thumb'])
                ->setFontSize($this->fontSize['thumb'])
                ->save(
                    $thumbPathPrefix . $projectRef . '/' . $this->filename,
                    100
                );

            //generate mobile avatar
            Avatar::create($projectName)
                ->setDimension($this->width['mobile'])
                ->setFontSize($this->fontSize['mobile'])
                ->save(
                    $mobilePathPrefix . $projectRef . '/' . $this->filename,
                    100
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
    protected function generateS3(string $projectRef, string $projectName): bool
    {
        try {
            $imageThumb = Avatar::create($projectName)
                ->setDimension($this->width['thumb'])
                ->setFontSize($this->fontSize['thumb'])
                ->getImageObject();

            $imageMobile = Avatar::create($projectName)
                ->setDimension($this->width['mobile'])
                ->setFontSize($this->fontSize['mobile'])
                ->getImageObject();

            $imageThumbEncoded = $imageThumb->encode(new JpegEncoder(100));  // encodes image as jpg with 100% quality
            $imageMobileEncoded = $imageMobile->encode(new JpegEncoder(100));  // encodes image as jpg with 100% quality

            // Then upload using Storage:put()
            Storage::disk('project_thumb')->put($projectRef . '/' . $this->filename, (string) $imageThumbEncoded);
            Storage::disk('project_mobile_logo')->put($projectRef . '/' . $this->filename, (string) $imageMobileEncoded);

            return true;
        } catch (Throwable $e) {
            Log::error('Error creating and uploading project avatar', ['exception' => $e]);
            return false;
        }
    }
}
