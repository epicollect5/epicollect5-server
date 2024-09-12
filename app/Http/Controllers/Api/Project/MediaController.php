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

    /**
     * @param ruleMedia $ruleMedia
     * @return JsonResponse|\Illuminate\Http\Response|StreamedResponse|null
     */
    public function getMedia(RuleMedia $ruleMedia)
    {
        // todo get the uuid if the media is entry media
        // so collectors can only view their own media
        // Check permissions
        $params = request()->all();

        // Validate request params
        $ruleMedia->validate($params);
        if ($ruleMedia->hasErrors()) {
            return Response::apiErrorCode(400, $ruleMedia->errors());
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
                //get storage prefix
                $storagePathPrefix = Storage::disk($format)->path('');
                //get file real path
                $realFilepath = $storagePathPrefix . $this->requestedProject()->ref . '/' . $params['name'];
                // Check if the file exists using real (absolute) path
                if (!File::exists($realFilepath)) {
                    throw new FileNotFoundException("File does not exist at path: " . $realFilepath);
                }

                $file = File::get($realFilepath);
                //stream only audio and video
                if ($inputType !== config('epicollect.strings.inputs_type.photo')) {
                    //serve as 206  partial response to load file faster
                    return Response::toMediaStream(request(), $realFilepath, $inputType);
                } else {
                    //photo response is the usual 200
                    $response = Response::make($file);
                    $response->header('Content-Type', $contentType);
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
     * @param ruleMedia $ruleMedia
     * @return JsonResponse|\Illuminate\Http\Response
     */
    public function getTempMedia(RuleMedia $ruleMedia)
    {
        $params = request()->all();
        // Validate request params
        $ruleMedia->validate($params);
        if ($ruleMedia->hasErrors()) {
            return Response::apiErrorCode(400, $ruleMedia->errors());
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
                    $response = Response::make($realFilepath);
                    $response->header('Content-Type', $contentType);
                    return $response;
                }
            } catch (Throwable $e) {
                Log::error('Temp media error', ['exception' => $e->getMessage()]);
                //imp: If the file is not found, see if we have it in the non-temp folders
                //imp: this covers the case when a user is editing an existing web entry
                //imp: We display the stored file, if no temp file available yet
                return $this->getMedia($ruleMedia);
            }
        }

        return Response::apiErrorCode(400, ['temp-media-controller' => ['ec5_69']]);
    }
}
