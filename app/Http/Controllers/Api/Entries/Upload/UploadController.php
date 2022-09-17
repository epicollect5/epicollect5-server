<?php

namespace ec5\Http\Controllers\Api\Entries\Upload;

use ec5\Http\Validation\Entries\Upload\RuleUpload as UploadValidator;

use ec5\Repositories\QueryBuilder\Entry\Upload\Create\BranchEntryRepository as BranchEntryCreateRepository;
use ec5\Repositories\QueryBuilder\Entry\Upload\Create\EntryRepository as EntryCreateRepository;
use ec5\Repositories\QueryBuilder\Stats\Entry\StatsRepository as EntryStatsRepository;

use ec5\Models\Entries\EntryStructure;

use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\Api\ApiRequest;

use Illuminate\Http\Request;
use Config;
use App;

class UploadController extends UploadControllerBase
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
     * UploadController constructor.
     * @param Request $request
     * @param ApiRequest $apiRequest
     * @param ApiResponse $apiResponse
     * @param EntryStructure $entryStructure
     * @param EntryCreateRepository $entryCreateRepository
     * @param BranchEntryCreateRepository $branchEntryCreateRepository
     * @param EntryStatsRepository $entryStatsRepository
     */
    public function __construct(
        Request $request,
        ApiRequest $apiRequest,
        ApiResponse $apiResponse,
        EntryStructure $entryStructure,
        EntryCreateRepository $entryCreateRepository,
        BranchEntryCreateRepository $branchEntryCreateRepository,
        EntryStatsRepository $entryStatsRepository
    ) {
        parent::__construct(
            $request,
            $apiRequest,
            $apiResponse,
            $entryStructure,
            $entryCreateRepository,
            $branchEntryCreateRepository,
            $entryStatsRepository
        );
    }

    /**
     * @param UploadValidator $uploadValidator
     * @return \Illuminate\Http\JsonResponse
     *
     */
    public function postUpload(UploadValidator $uploadValidator)
    {
        /* UPLOAD AND CHECK IT WAS SUCCESSFUL */
        if (!$this->upload($uploadValidator)) {
            //EC5Logger::error('Returning error response', $this->requestedProject, $this->errors);
            return $this->apiResponse->errorResponse(400, $this->errors);
        }

        time_nanosleep(0, 500000000);

        /* PASSED */
        // Send http status code 200, ok!
        return $this->apiResponse->successResponse('ec5_237');
    }

    public function postUploadBulk(UploadValidator $uploadValidator)
    {
        //kick out if in production, this route is only for debugging locally
        if (!App::isLocal()) {
            return $this->apiResponse->errorResponse(400, ['bulk-upload' => ['ec5_363']]);
        }
        return $this->postUpload($uploadValidator);
    }

    // public function postUploadPWA(UploadValidator $uploadValidator)
    // {
    //     //kick out if in production, this route is only for debugging locally
    //     if (!App::isLocal()) {
    //         return $this->apiResponse->errorResponse(400, ['pwa-upload' => ['ec5_91']]);
    //     }
    //     return $this->postUpload($uploadValidator);
    // }

    public function import(UploadValidator $uploadValidator)
    {
        $publicAccess = Config::get('ec5Strings.project_access.public');

        //If the project is public do not accept the request
        //Requests gets here when a projecy is public as the permission api middlweare let them through
        if ($this->requestedProject->access === $publicAccess) {
            return $this->apiResponse->errorResponse(400, ['errors' => ['ec5_256']]);
        }

        /* UPLOAD AND CHECK IT WAS SUCCESSFUL */
        if (!$this->upload($uploadValidator)) {
            return $this->apiResponse->errorResponse(400, $this->errors);
        }

        /* PASSED */
        // Send http status code 200, ok!

        return $this->apiResponse->successResponse('ec5_237');
    }
}
