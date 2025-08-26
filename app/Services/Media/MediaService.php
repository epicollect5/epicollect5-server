<?php

namespace ec5\Services\Media;

use ec5\DTO\ProjectDTO;
use ec5\Libraries\Utilities\Common;
use File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MediaService
{
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
            return $this->placeholderOrFallback($type);
        }

        // Resolve the disk and check existence via Storage
        $diskName   = Common::resolveDisk($format);
        $disk       = Storage::disk($diskName);
        $pathInDisk = $projectRef . '/' . $name;

        if (!$disk->exists($pathInDisk)) {
            return $this->placeholderOrFallback($type, $name);
        }

        $realFilepath = $disk->path($pathInDisk);

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
            return $this->placeholderOrFallback($type);
        }

        $path = $projectRef . '/' . $name;
        $disk = Storage::disk(Common::resolveDisk($format));

        if (!$disk->exists($path)) {
            return $this->placeholderOrFallback($type, $name);
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
                $stream = $disk->readStream($path);
                $imageContent = stream_get_contents($stream);
                fclose($stream);

                return response($imageContent, 200, [
                    'Content-Type' => $this->resolveContentType($type),
                ]);
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

    private function placeholderOrFallback(string $type, ?string $name = null)
    {
        $genericPlaceholderFilename   = config('epicollect.media.generic_placeholder.filename');
        $photoNotSyncedFilename     = config('epicollect.media.photo_not_synced_placeholder.filename');
        $projectAvatarFilename      = config('epicollect.media.project_avatar.filename');
        $contentType                = $this->resolveContentType($type);

        if ($type === config('epicollect.strings.inputs_type.photo')) {
            // If no name provided → always return the generic placeholder
            if (is_null($name)) {
                $file = Storage::disk('public')->get($genericPlaceholderFilename);
            } else {
                // If it's NOT the project avatar, return "not synced" placeholder
                if ($name !== $projectAvatarFilename) {
                    $file = Storage::disk('public')->get($photoNotSyncedFilename);
                } else {
                    // Special case: project avatar → normal placeholder
                    $file = Storage::disk('public')->get($genericPlaceholderFilename);
                }
            }

            return Response::make($file, 200, ['Content-Type' => $contentType]);
        }

        // Non-photo formats (audio/video) just return API 404
        return Response::apiErrorCode(404, ['media-service' => ['ec5_69']]);
    }


}
