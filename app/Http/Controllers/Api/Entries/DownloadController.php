<?php

namespace ec5\Http\Controllers\Api\Entries;

use ec5\Http\Controllers\Api\ApiRequest;
use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\Api\Entries\View\EntrySearchControllerBase;
use ec5\Http\Validation\Entries\Upload\RuleAnswers;
use ec5\Http\Validation\Entries\Search\RuleQueryString;
use ec5\Http\Validation\Entries\Download\RuleDownload;
use ec5\Repositories\QueryBuilder\Entry\Search\BranchEntryRepository;
use ec5\Repositories\QueryBuilder\Entry\Search\EntryRepository;
use ec5\Http\Validation\Entries\Upload\RuleDownloadTemplate;
use ec5\Http\Validation\Entries\Upload\RuleUploadHeaders;
use ec5\Services\DataMappingService;
use ec5\Services\DownloadEntriesService;
use Illuminate\Http\Request;
use ec5\Models\Eloquent\ProjectStructure;
use Auth;
use Illuminate\Support\Facades\Response;
use Storage;
use Cookie;
use Illuminate\Support\Str;
use ec5\Libraries\Utilities\Common;

class DownloadController extends EntrySearchControllerBase
{
    /*
    |--------------------------------------------------------------------------
    | Download Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the downloading of entry data (compressed using zip)
    |
    */

    protected $allowedSearchKeys;

    /**
     * DownloadController constructor.
     * @param Request $request
     * @param ApiRequest $apiRequest
     * @param ApiResponse $apiResponse
     * @param EntryRepository $entryRepository
     * @param BranchEntryRepository $branchEntryRepository
     * @param RuleQueryString $ruleQueryString
     * @param RuleAnswers $ruleAnswers
     */
    public function __construct(
        Request               $request,
        ApiRequest            $apiRequest,
        ApiResponse           $apiResponse,
        EntryRepository       $entryRepository,
        BranchEntryRepository $branchEntryRepository,
        RuleQueryString       $ruleQueryString,
        RuleAnswers           $ruleAnswers
    )
    {
        parent::__construct(
            $request,
            $apiRequest,
            $apiResponse,
            $entryRepository,
            $branchEntryRepository,
            $ruleQueryString,
            $ruleAnswers
        );

        $this->allowedSearchKeys = array_keys(config('epicollect.strings.download_data_entries'));
    }

    /**
     */
    public function index(Request $request, RuleDownload $ruleDownload)
    {
        $user = Auth::user();
        $params = $this->getRequestParams($request, config('epicollect.limits.entries_table.per_page_download'));
        $cookieName = config('epicollect.strings.cookies.download-entries');

        if ($user === null) {
            return $this->apiResponse->errorResponse(400, ['download-entries' => ['ec5_86']]);
        }
        // Validate the request params
        $ruleDownload->validate($params);
        if ($ruleDownload->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $this->ruleQueryString->errors());
        }
        //we send a "media-request" parameter in the query string with a timestamp. to generate a cookie with the same timestamp
        $timestamp = $request->query($cookieName);
        if ($timestamp) {
            //check if the timestamp is valid
            if (!Common::isValidTimestamp($timestamp) && strlen($timestamp) === 13) {
                return $this->apiResponse->errorResponse(404, ['download-entries' => ['ec5_29']]);
            }
        } else {
            //error no timestamp was passed
            return $this->apiResponse->errorResponse(404, ['download-entries' => ['ec5_29']]);
        }
        $projectDir = $this->getArchivePath($user);
        // Try and create the files
        return $this->createArchive($projectDir, $params, $timestamp);
    }

    private function sendArchive($filepath, $filename, $timestamp = null)
    {
        $cookieName = config('epicollect.strings.cookies.download-entries');
        //"If set to 0, or omitted, the cookie will expire at the end of the session (when the browser closes)."
        $mediaCookie = Cookie::make($cookieName, $timestamp, 0, null, null, false, false);
        Cookie::queue($mediaCookie);

        if (file_exists($filepath)) {
            return response()->download($filepath, $filename)->deleteFileAfterSend(true);
        } else {
            //this happens only when users are downloading the file, so send error as file
            //to keep the user on the dataviewer page.
            //because on the front end this is requested using window.location
            $filename = 'epicollect5-error.txt';
            $content = trans('status_codes.ec5_364');
            return response()->attachment($content, $filename);
        }
    }

    private function createArchive(string $projectDir, array $params, $timestamp)
    {
        $downloadEntriesService = new DownloadEntriesService(new DataMappingService());
        if (!$downloadEntriesService->createArchive($this->requestedProject, $projectDir, $params)) {
            return $this->apiResponse->errorResponse(400, ['download-entries' => ['ec5_83']]);
        }
        $zipName = $this->requestedProject->slug . '-' . $params['format'] . '.zip';
        return $this->sendArchive($projectDir . '/' . $zipName, $zipName, $timestamp);
    }

    private function getArchivePath($user)
    {
        // Setup storage
        $storage = Storage::disk('entries_zip');
        $storagePrefix = $storage->getDriver()->getAdapter()->getPathPrefix();
        $projectDir = $storagePrefix . $this->requestedProject->ref;
        //append user ID to handle concurrency -> MUST be logged in to download!
        return $projectDir . '/' . $user->id;
    }

}
