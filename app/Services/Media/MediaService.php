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
        $diskName = Common::resolveDisk($format);

        return match ($format) {
            config('epicollect.strings.media_formats.entry_original') =>
            Response::toEntryOriginalLocal($projectRef, $name, $type),

            config('epicollect.strings.media_formats.project_thumb') =>
            Response::toProjectThumbLocal($projectRef, $name),

            config('epicollect.strings.inputs_type.audio'),
            config('epicollect.strings.inputs_type.video') =>
            Response::toMediaStreamLocal(
                request(),
                Storage::disk($diskName)->path($projectRef . '/' . $name),
                $type
            ),
        };
    }


    private function serveS3(array $params, object $project)
    {
        $format = $params['format'];
        $type   = $params['type'];
        $name   = $params['name'] ?? null;

        try {
            return match ($format) {
                // audio
                config('epicollect.strings.inputs_type.audio') =>
                Response::toMediaStreamS3(
                    request(),
                    config("filesystems.disks.audio.root") . '/' . $project->ref . '/' . $name,
                    $type
                ),

                // video
                config('epicollect.strings.inputs_type.video') =>
                Response::toMediaStreamS3(
                    request(),
                    config("filesystems.disks.video.root") . '/' . $project->ref . '/' . $name,
                    $type
                ),

                // original photo
                config('epicollect.strings.media_formats.entry_original') =>
                Response::toEntryOriginalS3($project->ref, $name),

                // generated thumbnails
                config('epicollect.strings.media_formats.entry_thumb') =>
                Response::toEntryThumbS3($project->ref, $name),

                //project avatar
                config('epicollect.strings.media_formats.project_thumb') =>
                Response::toProjectThumbS3($project->ref, $name),

                //project avatar for mobile app
                config('epicollect.strings.media_formats.project_mobile_logo') =>
                Response::toProjectMobileLogoS3($project->ref, $name),

                // unknown formats
                default => Response::apiErrorCode(400, ['media-service' => ['ec5_103']])
            };
        } catch (Throwable $e) {
            Log::error('Cannot serve S3 media', ['exception' => $e]);
            return Response::apiErrorCode(400, ['media-service' => ['ec5_103']]);
        }
    }
}
