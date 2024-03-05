<?php

namespace ec5\Http\Controllers\Api\Entries\Upload;

use App;
use ec5\Services\Entries\EntriesUploadService;
use Illuminate\Http\JsonResponse;
use Log;
use Response;

class UploadAppController extends UploadControllerBase
{
    /*
    |--------------------------------------------------------------------------
    | Entry Upload Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the upload of entry data
    |
    */

    /**
     * @return JsonResponse
     */
    public function postUpload()
    {
        //Log::info('request', ['request' => request()->all()]);
        /* UPLOAD AND CHECK IT WAS SUCCESSFUL */
        if (!$this->entriesUploadService->upload()) {
            return Response::apiErrorCode(400, $this->entriesUploadService->errors);
        }
        time_nanosleep(0, 500000000);
        /* PASSED */
        // Send http status code 200, ok!
        return Response::apiSuccessCode('ec5_237');
    }

    public function postUploadBulk()
    {
        //kick out if in production, this route is only for debugging locally
        if (!App::isLocal()) {
            return Response::apiErrorCode(400, ['bulk-upload' => ['ec5_363']]);
        }
        $this->isBulkUpload = true;
        return $this->postUpload();
    }

    public function import()
    {
        $publicAccess = config('epicollect.strings.project_access.public');

        //If the project is public do not accept the request
        //Requests gets here when a project is public as the permission api middleware let them through
        if ($this->requestedProject()->access === $publicAccess) {
            return Response::apiErrorCode(400, ['errors' => ['ec5_256']]);
        }

        /* UPLOAD AND CHECK IT WAS SUCCESSFUL */
        $entriesUploadService = new EntriesUploadService($this->entryStructure, $this->ruleUpload, $this->isBulkUpload);
        if (!$entriesUploadService->upload()) {
            return Response::apiErrorCode(400, $entriesUploadService->errors);
        }

        /* PASSED */
        // Send http status code 200, ok!
        return Response::apiSuccessCode('ec5_237');
    }
}
