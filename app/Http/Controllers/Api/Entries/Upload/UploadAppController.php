<?php

namespace ec5\Http\Controllers\Api\Entries\Upload;

use App;
use ec5\Services\Entries\EntriesUploadService;
use Illuminate\Http\JsonResponse;
use Response;
use Throwable;

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
     * @throws Throwable
     */
    public function postUpload()
    {
        //Log::info('request', ['request' => request()->all()]);
        /* UPLOAD AND CHECK IT WAS SUCCESSFUL */
        if (!$this->entriesUploadService->upload()) {
            return Response::apiErrorCode(400, $this->entriesUploadService->errors);
        }
        //let the server breathe a bit
        sleep(config('epicollect.setup.api_sleep_time.entries'));
        /* PASSED */
        // Send http status code 200, ok!
        return Response::apiSuccessCode('ec5_237');
    }

    /**
     * @throws Throwable
     */
    public function postUploadBulk()
    {
        //kick out if in production, this route is only for debugging locally
        if (!App::isLocal()) {
            return Response::apiErrorCode(400, ['bulk-upload' => ['ec5_363']]);
        }
        $this->entriesUploadService->isBulkUpload = true;
        return $this->postUpload();
    }

    /**
     * @throws Throwable
     */
    public function import()
    {
        $publicAccess = config('epicollect.strings.project_access.public');

        //If the project is public do not accept the request
        //Requests gets here when a project is public as the permission api middleware let them through
        if ($this->requestedProject()->access === $publicAccess) {
            return Response::apiErrorCode(400, ['errors' => ['ec5_256']]);
        }

        /* UPLOAD AND CHECK IT WAS SUCCESSFUL */
        $entriesUploadService = new EntriesUploadService(
            $this->entryStructure,
            $this->ruleUpload,
            $this->fileMoverService
        );
        if (!$entriesUploadService->upload()) {
            return Response::apiErrorCode(400, $entriesUploadService->errors);
        }

        /* PASSED */
        // Send http status code 200, ok!
        return Response::apiSuccessCode('ec5_237');
    }
}
