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

    public function __construct()
    {
        $this->width = config('epicollect.media.project_avatar.width');
        $this->height = config('epicollect.media.project_avatar.height');
        $this->quality = config('epicollect.media.project_avatar.quality');
        $this->filename = config('epicollect.media.project_avatar.filename');
        $this->drivers = config('epicollect.media.project_avatar.driver');
        $this->fontSize = config('epicollect.media.project_avatar.font_size');
    }

    public function generate($projectRef, $projectName): bool
    {
        $driver = config('filesystems.default'); // or wherever you store your driver setting

        if ($driver === 's3') {
            return $this->generateS3($projectRef, $projectName);
        } else {
            return $this->generateLocal($projectRef, $projectName);
        }
    }

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
