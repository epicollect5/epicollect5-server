<?php

namespace ec5\Http\Controllers\Api\Project;

use ec5\Http\Validation\Media\RuleMedia;
use ec5\Services\Media\MediaService;
use ec5\Services\Media\TempMediaService;
use File;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\JsonResponse;
use Response;
use Storage;
use Log;
use ec5\Traits\Requests\RequestAttributes;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MediaController
{
    /*
    |--------------------------------------------------------------------------
    | Media Controller
    |--------------------------------------------------------------------------
    |
    | This controller serves media files
    |
    */
    use RequestAttributes;

    private RuleMedia $ruleMedia;
    private MediaService $mediaService;
    private TempMediaService $tempMediaService;

    public function __construct(
        RuleMedia $ruleMedia,
        MediaService $mediaService,
        TempMediaService $tempMediaService
    ) {
        $this->ruleMedia = $ruleMedia;
        $this->mediaService = $mediaService;
        $this->tempMediaService = $tempMediaService;
    }

    /**
     * Validates the current media request parameters.
     *
     * @return bool True if the request parameters are valid; false if validation errors are present.
     */
    private function isMediaRequestValid()
    {
        $params = request()->all();
        // Validate request params
        $this->ruleMedia->validate($params);
        if ($this->ruleMedia->hasErrors()) {
            return false;
        }
        return true;
    }

    /**
     * Serves a media file (photo, audio, or video) for a project from the configured storage driver.
     *
     * Validates the incoming media request and retrieves the requested file from either local storage or Amazon S3, depending on configuration. Returns an error response if validation fails or if the storage driver is unsupported.
     *
     * @return \Illuminate\Http\Response|StreamedResponse JSON error response, file response, or streamed media content.
     */
    public function getMedia()
    {
        if (!$this->isMediaRequestValid()) {
            return Response::apiErrorCode(400, $this->ruleMedia->errors());
        }

        return $this->mediaService->serve(request()->all(), $this->requestedProject());
    }

    /**
     * Retrieves a temporary media file from the configured storage driver.
     *
     * Validates the media request and serves the requested temporary media file
     * from local storage regardless of the application's configuration.
     * Returns an error response if validation fails or if the storage driver is unsupported.
     */
    public function getTempMedia(TempMediaService $service)
    {
        if (!$this->isMediaRequestValid()) {
            return Response::apiErrorCode(400, $this->ruleMedia->errors());
        }

        return $service->getTempMedia(request()->all(), $this->requestedProject());
    }

    /**
     * @param array $params Media request parameters, including 'type' and 'name'.
     * @return JsonResponse|BinaryFileResponse|StreamedResponse
     * Retrieves a temporary media file (photo, audio, or video) from local storage.
     *
     * If the specified temporary file exists, returns it with the appropriate content type. Audio and video files are streamed, while photos are returned as full content. If the file is not found or an error occurs, attempts to retrieve the corresponding permanent media file as a fallback. Returns a 400 error if no file name is provided.
     *
     */
    public function getTempMediaLocal(array $params)
    {
        $inputType = $params['type'];

        // Set up type and content type
        switch ($inputType) {
            case config('epicollect.strings.inputs_type.audio'):
                $contentType = config('epicollect.media.content_type.audio');
                break;
            case config('epicollect.strings.inputs_type.video'):
                $contentType = config('epicollect.media.content_type.video');
                break;
            default:
                $contentType = config('epicollect.media.content_type.photo');
        }
        // If a name was supplied, attempt to find file
        if (!empty($params['name'])) {
            // Attempt to retrieve media
            try {
                $filepathPrefix = config("filesystems.disks.temp.root").'/';
                //get file real path
                $realFilepath = $filepathPrefix . $inputType . '/' . $this->requestedProject()->ref . '/' . $params['name'];

                // Check if the file exists using absolute path
                if (!File::exists($realFilepath)) {
                    throw new FileNotFoundException("File does not exist at path: " . $realFilepath);
                }

                //stream only audio and video (not in unit tests!)
                if ($inputType !== config('epicollect.strings.inputs_type.photo')) {
                    return Response::toMediaStreamLocal(request(), $realFilepath, $inputType);
                } else {
                    //photo response is as usual
                    sleep(config('epicollect.setup.api_sleep_time.media'));
                    //we load the file in  memory, we are aware of the limitations but images are small
                    //around 500kb to 1MB max
                    return Response::make(
                        file_get_contents($realFilepath),
                        200,
                        ['Content-Type' => $contentType]
                    );
                }
            } catch (Throwable $e) {
                Log::info('Temp media error', ['exception' => $e->getMessage()]);
                /**
                 * Imp: If the file is not found, check for its existence in the non-temporary folders
                 * Imp: This handles the case when a user is editing an existing web entry
                 * Imp: If the temporary file is unavailable, display the stored file instead
                 */
                return $this->getMedia();
            }
        }

        return Response::apiErrorCode(400, ['temp-media-controller' => ['ec5_69']]);
    }
}
