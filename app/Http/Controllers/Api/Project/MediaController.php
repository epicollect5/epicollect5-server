<?php

namespace ec5\Http\Controllers\Api\Project;

use ec5\Http\Validation\Media\RuleMedia as MediaValidator;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\ProjectControllerBase;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Request;
use Exception;
use Response;
use Storage;
use Image;
use Log;
use ec5\Libraries\Utilities\MediaStreaming;

class MediaController extends ProjectControllerBase
{
    /**
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @param MediaValidator $mediaValidator
     */
    public function getMedia(Request $request, ApiResponse $apiResponse, MediaValidator $mediaValidator)
    {
        // todo: get the uuid if the media is entry media
        // so collectors can only view their own media
        // Check permissions

        $input = $request->all();
        $inputType = $input['type'];

        // Validate the options
        $mediaValidator->validate($input);
        if ($mediaValidator->hasErrors()) {
            return $apiResponse->errorResponse(400, $mediaValidator->errors());
        }

        $format = $request->query('format');
        if ($format === 'project_mobile_logo') {
            //randomly slow down api responses to avoid out of memory errors
            $delay = mt_rand(250000000, 500000000);
            time_nanosleep(0, $delay);
        }

        // Set up type and content type
        switch ($inputType) {
            case 'audio':
                $contentType = 'audio/mp4';
                $defaultName = 'ec5-placeholder-256x256.jpg';
                break;
            case 'video':
                $contentType = 'video/mp4';
                $defaultName = 'ec5-placeholder-256x256.jpg';
                break;
            default:
                $contentType = 'image/jpeg';
                $defaultName = 'ec5-placeholder-256x256.jpg';
        }

        // If a name was supplied, attempt to find file
        if (!empty($input['name'])) {
            // Attempt to retrieve media
            try {
                // Use the provided 'format' as the driver
                $file = Storage::disk($input['format'])->get($this->requestedProject->ref . '/' . $input['name']);
                //get storage real path
                $filepath = Storage::disk($input['format'])->getAdapter()->getPathPrefix();
                //get file real path
                $filepath = $filepath . $this->requestedProject->ref . '/' . $input['name'];
                //stream only audio and video
                if ($inputType !== 'photo') {
                    //serve as 206  partial response
                    $stream = new MediaStreaming($filepath, 'audio');
                    $stream->start();
                } else {
                    //photo response is the usual 200
                    $response = Response::make($file, 200);
                    $response->header("Content-Type", $contentType);
                    return $response;
                }
            } catch (FileNotFoundException $e) {

                if ($inputType === config('ec5Strings.inputs_type.photo')) {
                    //Return default placeholder image for photo questions
                    $file = Storage::disk('public')->get($defaultName);
                    $response = Response::make($file, 200);
                    $response->header("Content-Type", $contentType);
                    return $response;
                }

                //File not found i.e. not synced yet, send 404 for audio and video
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
        $response->header("Content-Type", $contentType);

        return $response;
    }

    /**
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @param MediaValidator $mediaValidator
     * @return \Illuminate\Http\JsonResponse
     */
    //imp: method is not used yet
    public function getApiMedia(Request $request, ApiResponse $apiResponse, MediaValidator $mediaValidator)
    {
        // todo get the uuid if the media is entry media
        // so collectors can only view their own media
        // Check permissions

        $input = $request->all();

        // Validate the options
        $mediaValidator->validate($input);
        if ($mediaValidator->hasErrors()) {
            return $apiResponse->errorResponse(400, $mediaValidator->errors());
        }


        $format = $request->query('format');
        if ($format === 'entry_thumb' || $format === 'project_mobile_logo') {
            //randomly slow down api responses to avoid out of memory errors
            $delay = mt_rand(250000000, 500000000);
            time_nanosleep(0, $delay);
        }

        // Set up type and content type
        switch ($input['type']) {
            case 'audio':
                $contentType = 'audio/mpeg';
                $defaultName = 'ec5-placeholder-256x256.jpg';
                break;
            case 'video':
                $contentType = 'video/mp4';
                $defaultName = 'ec5-placeholder-256x256.jpg';
                break;
            default:
                $contentType = 'image/jpeg';
                $defaultName = 'ec5-placeholder-256x256.jpg';
        }

        // If a name was supplied, attempt to find file
        if (!empty($input['name'])) {
            // Attempt to retrieve media
            try {
                // Use the provided 'format' as the driver
                $file = Storage::disk($input['format'])->get($this->requestedProject->ref . '/' . $input['name']);
                $response = Response::make($file, 200);
                $response->header("Content-Type", $contentType);

                //Throttle for 1/4 of a second so the server does not get smashed by media requests
                time_nanosleep(0, (int)(env('RESPONSE_DELAY_MEDIA_REQUEST', 250000000)));

                return $response;
            } catch (Exception $e) {
                //  Log::error('Cannot get media file', ['exception' => $e->getMessage()]);
                Log::error('Error getting media file', ['exception' => $e->getMessage()]);
            }
        }

        // Otherwise return default placeholder media
        $file = Storage::disk('public')->get($defaultName);
        $response = Response::make($file, 200);
        $response->header("Content-Type", $contentType);

        return $response;
    }

    /**
     * @param Request $request
     * @param ApiResponse $apiResponse
     * @param MediaValidator $mediaValidator
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function getTempMedia(Request $request, ApiResponse $apiResponse, MediaValidator $mediaValidator)
    {

        $input = $request->all();
        $inputType = $input['type'];

        // Validate the options
        $mediaValidator->validate($input);
        if ($mediaValidator->hasErrors()) {
            return $apiResponse->errorResponse(400, $mediaValidator->errors());
        }

        // Set up type and content type
        switch ($inputType) {
            case 'audio':
                $contentType = 'audio/mpeg';
                $defaultName = 'ec5-placeholder-256x256.jpg';
                break;
            case 'video':
                $contentType = 'video/mp4';
                $defaultName = 'ec5-placeholder-256x256.jpg';
                break;
            default:
                $contentType = 'image/jpeg';
                $defaultName = 'ec5-placeholder-256x256.jpg';
        }

        // If a name was supplied, attempt to find file
        if (!empty($input['name'])) {
            // Attempt to retrieve media
            try {
                // Use the provided 'format' as the driver
                $file = Storage::disk('temp')->get($inputType . '/' . $this->requestedProject->ref . '/' . $input['name']);
                //get storage real path
                $filepath = Storage::disk('temp')->getAdapter()->getPathPrefix();
                //get file real path
                $filepath = $filepath . $inputType . '/' . $this->requestedProject->ref . '/' . $input['name'];
                //stream only audio and video
                if ($inputType !== 'photo') {
                    $stream = new MediaStreaming($filepath, 'audio');
                    $stream->start();
                } else {
                    //photo response is as usual
                    $response = Response::make($file, 200);
                    $response->header('Content-Type', $contentType);
                    return $response;
                }
            } catch (Exception $e) {
                // If the file is not found, see if we have it in the non temp folders
                return $this->getMedia($request, $apiResponse, $mediaValidator);
            }
        }

        // Otherwise return default placeholder media
        $file = Storage::disk('public')->get($defaultName);
        $response = Response::make($file, 200);
        $response->header("Content-Type", $contentType);

        return $response;
    }
}
