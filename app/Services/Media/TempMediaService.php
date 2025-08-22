<?php

namespace ec5\Services\Media;

use ec5\DTO\ProjectDTO;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Throwable;
use Response;

class TempMediaService
{
    private MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function getTempMedia(array $params, ProjectDTO $project)
    {
        $driver = config('filesystems.default');

        // Currently only local is supported for temp media
        if (!in_array($driver, ['local', 's3'])) {
            Log::error('Storage driver not supported', ['driver' => $driver]);
            return Response::apiErrorCode(400, ['temp-media' => ['ec5_103']]);
        }

        return $this->getTempMediaLocal($params, $project);
    }

    public function getTempMediaLocal(array $params, ProjectDTO $project)
    {
        $type        = $params['type'] ?? null;
        $name        = $params['name'] ?? null;
        $projectRef  = $project->ref;
        $contentType = $this->resolveContentType($type);

        if (!$name) {
            return Response::apiErrorCode(400, ['temp-media' => ['ec5_69']]);
        }

        try {
            $realFilepath = config("filesystems.disks.temp.root")
                . '/' . $type
                . '/' . $projectRef
                . '/' . $name;

            if (!File::exists($realFilepath)) {
                throw new FileNotFoundException("File does not exist at path: $realFilepath");
            }

            // Stream audio/video, return photo as memory
            if ($type !== config('epicollect.strings.inputs_type.photo')) {
                return Response::toMediaStreamLocal(request(), $realFilepath, $type);
            }

            sleep(config('epicollect.setup.api_sleep_time.media'));
            return Response::make(
                file_get_contents($realFilepath),
                200,
                ['Content-Type' => $contentType]
            );

        } catch (Throwable $e) {
            Log::info('Temp media error', ['exception' => $e->getMessage()]);
            // fallback: load from permanent media
            return $this->mediaService->serve($params, $project);
        }
    }

    private function resolveContentType(string $type): string
    {
        return match ($type) {
            config('epicollect.strings.inputs_type.audio') => config('epicollect.media.content_type.audio'),
            config('epicollect.strings.inputs_type.video') => config('epicollect.media.content_type.video'),
            default => config('epicollect.media.content_type.photo'),
        };
    }
}
