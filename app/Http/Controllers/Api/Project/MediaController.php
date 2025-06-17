<?php

namespace ec5\Http\Controllers\Api\Project;

use ec5\Http\Validation\Media\RuleMedia;
use File;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\JsonResponse;
use Response;
use Storage;
use Log;
use ec5\Traits\Requests\RequestAttributes;
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

    /**
     * Initializes the MediaController with a RuleMedia validation instance.
     *
     * @param RuleMedia $ruleMedia The media validation rule instance.
     */
    public function __construct(RuleMedia $ruleMedia)
    {
        $this->ruleMedia = $ruleMedia;
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
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse JSON error response, file response, or streamed media content.
     */
    public function getMedia()
    {
        if (!$this->isMediaRequestValid()) {
            return Response::apiErrorCode(400, $this->ruleMedia->errors());
        }

        $driver = config('filesystems.default'); // or wherever you store your driver setting
        if ($driver === 's3') {
            return $this->getMediaS3(request()->all());
        }
        if ($driver === 'local') {
            return $this->getMediaLocal(request()->all());
        }

        Log::error('Storage driver not supported', ['driver' => $driver]);
        return Response::apiErrorCode(400, ['media-controller' => ['ec5_103']]);
    }

    /**
     * Retrieves a media file (photo, audio, or video) from local storage and returns it as an HTTP response.
     *
     * If the requested file is a photo thumbnail, introduces a random delay to mitigate server load. Streams audio and video files with partial content responses, and returns photo files with full content. If the file is not found, returns a default placeholder image for photos or a 404 error for audio and video. If no filename is provided, always returns the default placeholder image.
     *
     * @param array $params Parameters specifying media type, format, and filename.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse|null The HTTP response containing the media file, a placeholder image, or an error.
     */
    public function getMediaLocal($params)
    {
        $inputType = $params['type'];
        $format = $params['format'];
        if ($format === config('epicollect.media.formats.entry_thumb')) {
            //randomly slow down api responses for photo thumbs to avoid out of memory errors
            $delay = mt_rand(250000000, 500000000);
            time_nanosleep(0, $delay);
        }

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
        $defaultName = config('epicollect.media.photo_placeholder.filename');

        // If a name was supplied, attempt to find file
        if (!empty($params['name'])) {
            // Attempt to retrieve media
            try {
                //get storage prefix
                $storagePathPrefix = Storage::disk($format)->path('');
                //get file real path
                $realFilepath = $storagePathPrefix . $this->requestedProject()->ref . '/' . $params['name'];
                // Check if the file exists using real (absolute) path
                if (!File::exists($realFilepath)) {
                    throw new FileNotFoundException("File does not exist at path: " . $realFilepath);
                }

                //stream only audio and video
                if ($inputType !== config('epicollect.strings.inputs_type.photo')) {
                    //serve as 206  partial response to load file faster
                    return Response::toMediaStream(request(), $realFilepath, $inputType);
                } else {
                    //photo response is the usual 200
                    $content = file_get_contents($realFilepath);
                    $response = Response::make($content);
                    $response->header('Content-Type', $contentType);
                    sleep(config('epicollect.setup.api_sleep_time.media'));
                    return $response;
                }
            } catch (FileNotFoundException) {
                if ($inputType === config('epicollect.strings.inputs_type.photo')) {
                    //Return default placeholder image for photo questions
                    $file = Storage::disk('public')->get($defaultName);
                    $response = Response::make($file);
                    $response->header('Content-Type', $contentType);
                    return $response;
                }

                //File not found i.e., not synced yet, send 404 for audio and video
                $error['api-media-controller'] = ['ec5_69'];
                return Response::apiErrorCode(404, $error);
            } catch (Throwable $e) {
                Log::error('Cannot get media file', ['exception' => $e]);
            }
        }

        // Otherwise return default placeholder media
        $file = Storage::disk('public')->get($defaultName);
        $response = Response::make($file);
        $response->header('Content-Type', $contentType);

        return $response;
    }

    /**
     * Retrieves a media file from Amazon S3 storage and returns it as an HTTP response.
     *
     * Streams audio and video files or returns photo files with the appropriate content type. If the requested file does not exist, returns a default placeholder image for photos or a 404 error for audio and video. Introduces a random delay for photo thumbnail requests. Logs and handles exceptions, always returning a valid HTTP response.
     *
     * @param array $params Media request parameters, including 'type', 'format', and 'name'.
     * @return \Illuminate\Http\Response HTTP response containing the requested media or a fallback.
     */
    public function getMediaS3($params)
    {
        $inputType = $params['type'];
        $format = $params['format'];
        $defaultName = config('epicollect.media.photo_placeholder.filename');

        if ($format === config('epicollect.media.formats.entry_thumb')) {
            time_nanosleep(0, mt_rand(250000000, 500000000));
        }

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

        try {
            if (!empty($params['name'])) {
                $path = $this->requestedProject()->ref . '/' . $params['name'];
                $disk = Storage::disk($format);

                if (!$disk->exists($path)) {
                    throw new FileNotFoundException("File not found on S3: $path");
                }

                // Stream for audio, video, standard 200 for photo
                if ($inputType !== config('epicollect.strings.inputs_type.photo')) {
                    // For S3, get a streamable response (Laravel >=9 supports streamDownload())
                    return Response::toMediaStream(request(), $path, $inputType);
                } else {
                    // Photo: normal 200 OK
                    $file = $disk->get($path);
                    $response = Response::make($file);
                    $response->header('Content-Type', $contentType);
                    sleep(config('epicollect.setup.api_sleep_time.media'));
                    return $response;
                }
            }
        } catch (FileNotFoundException) {
            if ($inputType === config('epicollect.strings.inputs_type.photo')) {
                $file = Storage::disk('public')->get($defaultName);
                $response = Response::make($file);
                $response->header('Content-Type', $contentType);
                return $response;
            }

            $error['api-media-controller'] = ['ec5_69'];
            return Response::apiErrorCode(404, $error);
        } catch (Throwable $e) {
            Log::error('Cannot get S3 media file', ['exception' => $e]);
        }

        $file = Storage::disk('public')->get($defaultName);
        $response = Response::make($file);
        $response->header('Content-Type', $contentType);
        return $response;
    }


    /**
     * Retrieves a temporary media file from the configured storage driver.
     *
     * Validates the media request and serves the requested temporary media file from either local storage or Amazon S3, depending on the application's configuration. Returns an error response if validation fails or if the storage driver is unsupported.
     *
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse JSON error response or media file response.
     */
    public function getTempMedia()
    {
        if (!$this->isMediaRequestValid()) {
            return Response::apiErrorCode(400, $this->ruleMedia->errors());
        }

        $driver = config('filesystems.default'); // or wherever you store your driver setting

        if ($driver === 's3') {
            return $this->getTempMediaS3(request()->all());
        }

        if ($driver === 'local') {
            return $this->getTempMediaLocal(request()->all());
        }

        Log::error('Storage driver not supported', ['driver' => $driver]);
        return Response::apiErrorCode(400, ['temp-media-controller' => ['ec5_103']]);
    }

    /**
     * Retrieves a temporary media file (photo, audio, or video) from local storage.
     *
     * If the specified temporary file exists, returns it with the appropriate content type. Audio and video files are streamed, while photos are returned as full content. If the file is not found or an error occurs, attempts to retrieve the corresponding permanent media file as a fallback. Returns a 400 error if no file name is provided.
     *
     * @param array $params Media request parameters, including 'type' and 'name'.
     * @return JsonResponse|\Illuminate\Http\Response The media file response, a fallback response, or an error response.
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
                // Use the provided 'format' as the driver
                $filepathPrefix = Storage::disk('temp')->path('');
                //get file real path
                $realFilepath = $filepathPrefix . $inputType . '/' . $this->requestedProject()->ref . '/' . $params['name'];

                // Check if the file exists using absolute path
                if (!File::exists($realFilepath)) {
                    throw new FileNotFoundException("File does not exist at path: " . $realFilepath);
                }

                //stream only audio and video (not in unit tests!)
                if ($inputType !== config('epicollect.strings.inputs_type.photo')) {
                    return Response::toMediaStream(request(), $realFilepath, $inputType);
                } else {
                    //photo response is as usual
                    $content = file_get_contents($realFilepath);
                    $response = Response::make($content);
                    $response->header('Content-Type', $contentType);
                    return $response;
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

    /**
     * Retrieves a temporary media file from S3 storage and returns it as an HTTP response.
     *
     * If the requested file exists, streams audio and video files or returns photo files with the correct content type.
     * If the file is not found or an error occurs, falls back to retrieving the permanent media file.
     * Returns a 400 error if no file name is provided.
     *
     * @param array $params Media request parameters, including 'type' and 'name'.
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function getTempMediaS3($params)
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
                $path = $inputType. '/' . $this->requestedProject()->ref . '/' . $params['name'];
                $disk = Storage::disk('temp');

                // Check if the file exists using absolute path
                if (!$disk->exists($path)) {
                    Log::error($disk->url($path));
                    throw new FileNotFoundException("File not found on S3: $path");
                }

                //stream only audio and video (not in unit tests!)
                if ($inputType !== config('epicollect.strings.inputs_type.photo')) {
                    return Response::toMediaStream(request(), $path, $inputType);
                } else {
                    //photo response is as usual
                    $file = $disk->get($path);
                    $response = Response::make($file);
                    $response->header('Content-Type', $contentType);
                    return $response;
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
