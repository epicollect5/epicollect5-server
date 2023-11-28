<?php

namespace ec5\Http\Controllers\Api\Project;

use ec5\Http\Validation\Media\RuleMedia;
use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\ProjectControllerBase;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\App;
use Response;
use Storage;
use Log;
use ec5\Libraries\Utilities\MediaStreaming;

class MediaController extends ProjectControllerBase
{
    /*
    |--------------------------------------------------------------------------
    | Media Controller
    |--------------------------------------------------------------------------
    |
    | This controller serves media files
    |
    */

    /**
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @param ruleMedia $ruleMedia
     * @return JsonResponse|\Illuminate\Http\Response
     * @throws FileNotFoundException
     */
    public function getMedia(Request $request, ApiResponse $apiResponse, RuleMedia $ruleMedia)
    {
        // todo get the uuid if the media is entry media
        // so collectors can only view their own media
        // Check permissions
        $params = $request->all();

        // Validate request params
        $ruleMedia->validate($params);
        if ($ruleMedia->hasErrors()) {
            return $apiResponse->errorResponse(400, $ruleMedia->errors());
        }

        $inputType = $params['type'];
        $format = $params['format'];
        if ($format === config('epicollect.media.project_mobile_logo')) {
            //randomly slow down api responses to avoid out of memory errors
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
                // Use the provided 'format' as the driver
                $file = Storage::disk($format)->get($this->requestedProject->ref . '/' . $params['name']);
                //get storage real path
                $filepath = Storage::disk($format)->getAdapter()->getPathPrefix();
                //get file real path
                $filepath = $filepath . $this->requestedProject->ref . '/' . $params['name'];
                //stream only audio and video
                if ($inputType !== config('epicollect.strings.inputs_type.photo')) {
                    //serve as 206  partial response
                    $stream = new MediaStreaming($filepath, $inputType);
                    $stream->start();
                } else {
                    //photo response is the usual 200
                    $response = Response::make($file, 200);
                    $response->header('Content-Type', $contentType);
                    return $response;
                }
            } catch (FileNotFoundException $e) {
                if ($inputType === config('epicollect.strings.inputs_type.photo')) {
                    //Return default placeholder image for photo questions
                    $file = Storage::disk('public')->get($defaultName);
                    $response = Response::make($file, 200);
                    $response->header('Content-Type', $contentType);
                    return $response;
                }

                //File not found i.e., not synced yet, send 404 for audio and video
                $error['api-media-controller'] = ['ec5_69'];
                return $apiResponse->errorResponse(404, $error);
            } catch (Exception $e) {
                Log::error('Cannot get media file', ['exception' => $e]);
            }
        }

        //todo: the below could be removed?
        // Otherwise return default placeholder media
        $file = Storage::disk('public')->get($defaultName);
        $response = Response::make($file, 200);
        $response->header('Content-Type', $contentType);

        return $response;
    }

    /**
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @param ruleMedia $ruleMedia
     * @return JsonResponse|\Illuminate\Http\Response
     * @throws FileNotFoundException
     */
    public function getTempMedia(Request $request, ApiResponse $apiResponse, RuleMedia $ruleMedia)
    {
        $params = $request->all();
        // Validate request params
        $ruleMedia->validate($params);
        if ($ruleMedia->hasErrors()) {
            return $apiResponse->errorResponse(400, $ruleMedia->errors());
        }

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
        $defaultName = config('epicollect.media.photo_placeholder.filename');
        // If a name was supplied, attempt to find file
        if (!empty($params['name'])) {
            // Attempt to retrieve media
            try {
                // Use the provided 'format' as the driver
                $file = Storage::disk('temp')->get($inputType . '/' . $this->requestedProject->ref . '/' . $params['name']);
                //get storage real path
                $filepath = Storage::disk('temp')->getAdapter()->getPathPrefix();
                //get file real path
                $filepath = $filepath . $inputType . '/' . $this->requestedProject->ref . '/' . $params['name'];
                //stream only audio and video (not in unit tests!)
                if ($inputType !== config('epicollect.strings.inputs_type.photo') && !App::environment('testing')) {
                    //in tests, just return a 200 response as there are issue with headers()
                    //todo: re-assess after updating laravel and phpunit
                    $stream = new MediaStreaming($filepath, $inputType);
                    $stream->start();
                } else {
                    //photo response is as usual
                    $response = Response::make($file, 200);
                    $response->header('Content-Type', $contentType);
                    return $response;
                }
            } catch (Exception $e) {
                Log::error('Streaming error', ['exception' => $e->getMessage()]);
                // If the file is not found, see if we have it in the non-temp folders
                return $this->getMedia($request, $apiResponse, $ruleMedia);
            }
        }

        // Otherwise return default placeholder media
        $file = Storage::disk('public')->get($defaultName);
        $response = Response::make($file, 200);
        $response->header('Content-Type', $contentType);

        return $response;
    }
}
