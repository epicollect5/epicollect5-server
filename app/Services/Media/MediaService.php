<?php

namespace ec5\Services\Media;

use ec5\DTO\ProjectDTO;
use ec5\Libraries\Utilities\Common;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MediaService
{
    /**
     * Serve media based on driver (local or s3)
     */
    public function serve(array $params, ProjectDTO $project, bool $isExportMediaRequest = false)
    {
        $driver = config('filesystems.default');

        return match ($driver) {
            'local' => $this->serveLocal($params, $project),
            's3'    => $this->serveS3($params, $project, $isExportMediaRequest),
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
    private function serveS3(array $params, object $project, bool $isExportMediaRequest)
    {
        $format = $params['format'];
        $type   = $params['type'];
        $name   = $params['name'] ?? null;

        try {
            return match ($format) {
                // real files
                'entry_original', 'audio', 'video', 'project_thumb'
                => $this->serveS3File($type, $format, $project->ref, $name, $isExportMediaRequest),

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
    private function serveS3File(
        string $type,
        string $format,
        string $projectRef,
        ?string $name,
        bool $isExportMediaRequest
    ) {
        if (!$name) {
            return $this->placeholderOrFallback($type);
        }

        $path = $projectRef . '/' . $name;
        $diskName = Common::resolveDisk($format);
        $disk = Storage::disk($diskName);

        if (!$disk->exists($path)) {
            return $this->placeholderOrFallback($type, $name);
        }

        // For export media on S3, large original/audio/video files can be offloaded
        // to a short-lived presigned URL after all app-level checks have passed.
        if ($this->shouldRedirectS3ExportMedia($format, $isExportMediaRequest)) {
            return $this->redirectToTemporaryS3Url($diskName, $path, $format);
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
                $stream = $disk->readStream($path);
                $imageContent = stream_get_contents($stream);
                fclose($stream);

                // immutable when URL carries ?v= version token, 24 hours otherwise:
                // we want to leverage browser caching when possible,
                // but we also want to make sure that user-submitted content (entry photos)
                // is not server stale for more than 24 hours
                $cacheControl = request('v')
                    ? config('epicollect.media.cache_control.always')
                    : config('epicollect.media.cache_control.24h');

                return response($imageContent, 200, [
                    'Content-Type' => $this->resolveContentType($type),
                    'Cache-Control' => $cacheControl,
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

        // Read file content directly so it's accessible in tests and streaming contexts
        $fileContent = file_get_contents($realFilepath);
        $cacheControl = request('v')
            ? config('epicollect.media.cache_control.always')
            : config('epicollect.media.cache_control.24h');

        return response($fileContent, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => $cacheControl,
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

            return Response::make($file, 200, [
                'Content-Type' => $contentType,
                'Cache-Control' => config('epicollect.media.cache_control.always')
            ]);
        }

        // Non-photo formats (audio/video) just return API 404
        return Response::apiErrorCode(404, ['media-service' => ['ec5_69']]);
    }

    private function shouldRedirectS3ExportMedia(string $format, bool $isExportMediaRequest): bool
    {
        if (!$isExportMediaRequest || !config('epicollect.setup.api.export_media_s3_redirect_enabled')) {
            return false;
        }

        // Keep generated assets and non-export routes on the existing application response path.
        return in_array($format, [
            config('epicollect.strings.media_formats.entry_original'),
            config('epicollect.strings.inputs_type.audio'),
            config('epicollect.strings.inputs_type.video'),
        ], true);
    }

    private function redirectToTemporaryS3Url(string $diskName, string $path, string $format): RedirectResponse
    {
        // Do not cache the redirect itself: the presigned destination is short-lived and
        // should be resolved fresh when a client asks for the export media endpoint.
        $temporaryUrl = Storage::disk($diskName)->temporaryUrl(
            $path,
            now()->addMinutes($this->resolveRedirectTTLMinutes($format))
        );

        return redirect()->away($temporaryUrl, 302, [
            'Cache-Control' => config('epicollect.media.cache_control.never'),
        ]);
    }

    private function resolveRedirectTTLMinutes(string $format): int
    {
        return match ($format) {
            config('epicollect.strings.media_formats.entry_original')
            => config('epicollect.setup.api.export_media_s3_redirect_ttl_entry_original'),
            config('epicollect.strings.inputs_type.audio')
            => config('epicollect.setup.api.export_media_s3_redirect_ttl_audio'),
            config('epicollect.strings.inputs_type.video')
            => config('epicollect.setup.api.export_media_s3_redirect_ttl_video'),
            default => 10,
        };
    }
}
