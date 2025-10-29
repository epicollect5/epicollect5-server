<?php

namespace ec5\Services\Media;

use ec5\DTO\ProjectDTO;
use ec5\Libraries\Utilities\Common;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MediaService
{
    private PhotoRendererService $photoRendererService;

    public function __construct(PhotoRendererService $photoRendererService)
    {
        $this->photoRendererService = $photoRendererService;
    }
    /**
     * Serve media based on driver (local or s3)
     */
    public function serve(array $params, ProjectDTO $project)
    {
        $driver = config('filesystems.default');

        return match ($driver) {
            'local' => $this->serveLocal($params, $project),
            's3'    => $this->serveS3($params, $project),
            default => Response::apiErrorCode(400, ['media-service' => ['ec5_103']]),
        };
    }

    /**
     * Serve local media
     */
    private function serveLocal(array $params, object $project)
    {
        $format = $params['format'];
        $type   = $params['type'];
        $name   = $params['name'] ?? null;

        try {
            return match ($format) {
                // real files
                'entry_original', 'audio', 'video', 'project_thumb'
                => $this->serveLocalFile($type, $format, $project->ref, $name),

                // generated
                'entry_thumb'
                => Response::toEntryThumbLocal($project->ref, $name),

                'project_mobile_logo'
                => Response::toProjectMobileLogoLocal($project->ref, $name),

                default => Response::apiErrorCode(400, ['media-service' => ['ec5_103']]),
            };
        } catch (Throwable $e) {
            Log::error('Cannot serve local media', ['exception' => $e]);
            return Response::apiErrorCode(400, ['media-service' => ['ec5_103']]);
        }
    }

    /**
     * Serve a local real file
     */
    public function serveLocalFile(string $type, string $format, string $projectRef, ?string $name)
    {
        if (!$name) {
            return $this->photoRendererService->placeholderOrFallback($type);
        }

        // Resolve the disk and check existence via Storage
        $diskName   = Common::resolveDisk($format);
        $disk       = Storage::disk($diskName);
        $pathInDisk = $projectRef . '/' . $name;

        $resolvedPath = $this->photoRendererService->resolvePhotoPath($disk, $pathInDisk);

        if (!$resolvedPath) {
            return $this->photoRendererService->placeholderOrFallback($type, $name);
        }

        $realFilepath = $disk->path($resolvedPath);

        return $this->buildResponse($type, $realFilepath);
    }


    /**
     * Serve S3 media
     */
    private function serveS3(array $params, object $project)
    {
        $format = $params['format'];
        $type   = $params['type'];
        $name   = $params['name'] ?? null;

        try {
            return match ($format) {
                // real files
                'entry_original', 'audio', 'video', 'project_thumb'
                => $this->serveS3File($type, $format, $project->ref, $name),

                // generated
                'entry_thumb'
                => Response::toEntryThumbS3($project->ref, $name),

                'project_mobile_logo'
                => Response::toProjectMobileLogoS3($project->ref, $name),

                default => Response::apiErrorCode(400, ['media-service' => ['ec5_103']]),
            };
        } catch (Throwable $e) {
            Log::error('Cannot serve S3 media', ['exception' => $e]);
            return Response::apiErrorCode(400, ['media-service' => ['ec5_103']]);
        }
    }

    /**
     * Serve a S3 real file
     */
    private function serveS3File(string $type, string $format, string $projectRef, ?string $name)
    {
        if (!$name) {
            return $this->photoRendererService->placeholderOrFallback($type);
        }

        $path = $projectRef . '/' . $name;
        $disk = Storage::disk(Common::resolveDisk($format));
        $resolvedPath = $this->photoRendererService->resolvePhotoPath($disk, $path);

        if (!$resolvedPath) {
            return $this->photoRendererService->placeholderOrFallback($type, $name);
        }

        switch ($format) {
            case config('epicollect.strings.inputs_type.audio'):
                $diskRoot = config("filesystems.disks.audio.root").'/';
                return Response::toMediaStreamS3(request(), $diskRoot.$path, $type);
            case config('epicollect.strings.inputs_type.video'):
                $diskRoot = config("filesystems.disks.video.root").'/';
                return Response::toMediaStreamS3(request(), $diskRoot.$path, $type);
            default:
                // photo/avatar full response
                sleep(config('epicollect.setup.api_sleep_time.media'));

                // Convert to JPEG if needed
                $imageContent = $this->photoRendererService->getAsJpeg(
                    $disk,
                    $resolvedPath,
                    config('epicollect.media.quality.jpg', 90)
                );

                return response($imageContent, 200, array_merge(
                    ['Content-Type' => $this->resolveContentType($type)]
                ));
        }
    }

    /**
     * Build response for local file
     */
    private function buildResponse(string $type, string $realFilepath)
    {
        $contentType = $this->resolveContentType($type);

        if ($type !== config('epicollect.strings.inputs_type.photo')) {
            return Response::toMediaStreamLocal(request(), $realFilepath, $type);
        }

        sleep(config('epicollect.setup.api_sleep_time.media'));
        return Response::make(file_get_contents($realFilepath), 200, [
            'Content-Type' => $contentType
        ]);
    }

    /**
     * Resolve content type from type
     */
    private function resolveContentType(string $type): string
    {
        return match ($type) {
            config('epicollect.strings.inputs_type.audio')
            => config('epicollect.media.content_type.audio'),
            config('epicollect.strings.inputs_type.video')
            => config('epicollect.media.content_type.video'),
            default
            => config('epicollect.media.content_type.photo'),
        };
    }

}
