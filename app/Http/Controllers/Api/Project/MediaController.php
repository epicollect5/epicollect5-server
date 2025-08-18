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
     * @return \Illuminate\Http\Response|StreamedResponse JSON error response, file response, or streamed media content.
     */
    public function getMedia()
    {
        if (!$this->isMediaRequestValid()) {
            return Response::apiErrorCode(400, $this->ruleMedia->errors());
        }

        $driver = config('filesystems.default');
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
     * @return JsonResponse|BinaryFileResponse|StreamedResponse
     */
    public function getMediaLocal(array $params)
    {
        $photoPlaceholderFilename = config('epicollect.media.photo_placeholder.filename');
        $inputType = $params['type'];
        $format = $params['format'];
        if ($format === config('epicollect.strings.media_formats.entry_thumb')) {
            //randomly slow down api responses for photo thumbs to avoid out of memory errors
            $delay = mt_rand(250000000, 500000000);
            time_nanosleep(0, $delay);

            //build thumb at run time from original
            return Response::toEntryThumbLocal(
                $this->requestedProject()->ref,
                $params['name']
            );
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

        // If a name was supplied, attempt to find file
        if (!empty($params['name'])) {
            // Attempt to retrieve media
            try {
                //get storage prefix
                $storagePathPrefix = config("filesystems.disks.$format.root").'/';
                //get file real path
                $realFilepath = $storagePathPrefix . $this->requestedProject()->ref . '/' . $params['name'];
                // Check if the file exists using real (absolute) path
                if (!File::exists($realFilepath)) {
                    throw new FileNotFoundException("File does not exist at path: " . $realFilepath);
                }

                //stream only audio and video
                if ($inputType !== config('epicollect.strings.inputs_type.photo')) {
                    //serve as 206  partial response to load file faster
                    return Response::toMediaStreamLocal(request(), $realFilepath, $inputType);
                } else {
                    //photo response is the usual 200
                    sleep(config('epicollect.setup.api_sleep_time.media'));
                    //we load the file in  memory, we are aware of the limitations but images are small
                    //around 500kb to 1MB max
                    return Response::make(
                        file_get_contents($realFilepath),
                        200,
                        ['Content-Type' => $contentType]
                    );
                }
            } catch (FileNotFoundException) {
                if ($inputType === config('epicollect.strings.inputs_type.photo')) {
                    if ($params['name'] !== config('epicollect.media.project_avatar.filename')) {
                        //Return default placeholder image for photo questions
                        $photoNotSyncedFilename = config('epicollect.media.photo_not_synced_placeholder.filename');
                        $file = Storage::disk('public')->get($photoNotSyncedFilename);
                    } else {
                        //Return default placeholder image for logo
                        $file = Storage::disk('public')->get($photoPlaceholderFilename);
                    }
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
        $file = Storage::disk('public')->get($photoPlaceholderFilename);
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
     */
    public function getMediaS3(array $params)
    {
        $inputType = $params['type'];
        $format = $params['format'];
        $photoPlaceholderFilename = config('epicollect.media.photo_placeholder.filename');
        $photoNotSyncedFilename = config('epicollect.media.photo_not_synced_placeholder.filename');


        if ($format === config('epicollect.strings.media_formats.entry_thumb')) {
            time_nanosleep(0, mt_rand(250000000, 500000000));
            return Response::toEntryThumbS3(
                $this->requestedProject()->ref,
                $params['name']
            );
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
                    $path = 'app/entries/' . $format . '/' . $path;
                    return Response::toMediaStreamS3(request(), $path, $inputType);
                } else {
                    // Photo: normal 200 OK
                    sleep(config('epicollect.setup.api_sleep_time.media'));
                    // For S3, get a streamable response
                    $stream = Storage::disk($format)->readStream($path);
                    $imageContent = stream_get_contents($stream);
                    fclose($stream); // Close the stream manually
                    //we load the images in memory, we are aware of the limitations but images are small
                    //around 500kb to 1MB max
                    //otherwise it would be -> return $disk->response($path, null, ['Content-Type' => $contentType]);
                    return response($imageContent, 200, [
                        'Content-Type' => $contentType,
                    ]);
                }
            }
        } catch (FileNotFoundException) {
            if ($inputType === config('epicollect.strings.inputs_type.photo')) {
                if ($params['name'] !== config('epicollect.media.project_avatar.filename')) {
                    //Return default placeholder image for photo questions
                    $file = Storage::disk('public')->get($photoNotSyncedFilename);
                } else {
                    //Return default placeholder image for project logo
                    $file = Storage::disk('public')->get($photoPlaceholderFilename);
                }
                $response = Response::make($file);
                $response->header('Content-Type', $contentType);
                return $response;
            }

            $error['api-media-controller'] = ['ec5_69'];
            return Response::apiErrorCode(404, $error);
        } catch (Throwable $e) {
            Log::error('Cannot get S3 media file', ['exception' => $e]);
        }

        $file = Storage::disk('public')->get($photoPlaceholderFilename);
        $response = Response::make($file);
        $response->header('Content-Type', $contentType);
        return $response;
    }

    /**
     * Retrieves a temporary media file from the configured storage driver.
     *
     * Validates the media request and serves the requested temporary media file
     * from local storage regardless of the application's configuration.
     * Returns an error response if validation fails or if the storage driver is unsupported.
     *
     * @return \Illuminate\Http\Response|StreamedResponse JSON error response or media file response.
     */
    public function getTempMedia()
    {
        if (!$this->isMediaRequestValid()) {
            return Response::apiErrorCode(400, $this->ruleMedia->errors());
        }

        $driver = config('filesystems.default');

        //Currently, we store the temp files locally only, however, in the future we might want to store them on S3 as well
        if ($driver === 's3') {
            return $this->getTempMediaLocal(request()->all());
        }

        if ($driver === 'local') {
            return $this->getTempMediaLocal(request()->all());
        }

        Log::error('Storage driver not supported', ['driver' => $driver]);
        return Response::apiErrorCode(400, ['temp-media-controller' => ['ec5_103']]);
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
