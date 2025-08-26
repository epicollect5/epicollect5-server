<?php

namespace ec5\Http\Controllers\Api\Project;

use ec5\Http\Validation\Media\RuleMedia;
use ec5\Services\Media\MediaService;
use ec5\Services\Media\TempMediaService;
use Response;
use ec5\Traits\Requests\RequestAttributes;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    public function getTempMedia()
    {
        if (!$this->isMediaRequestValid()) {
            return Response::apiErrorCode(400, $this->ruleMedia->errors());
        }

        return $this->tempMediaService->getTempMedia(
            request()->all(),
            $this->requestedProject()
        );
    }
}
