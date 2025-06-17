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

    public function __construct(RuleMedia $ruleMedia)
    {
        $this->ruleMedia = $ruleMedia;
    }

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
     * @return JsonResponse|\Illuminate\Http\Response|StreamedResponse|null
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
     * @return JsonResponse|\Illuminate\Http\Response
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
