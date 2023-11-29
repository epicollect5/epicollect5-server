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
use ec5\Http\Validation\Entries\Upload\RuleUploadTemplate;
use ec5\Http\Validation\Entries\Upload\RuleUploadHeaders;
use ec5\Services\DataMappingService;
use ec5\Services\DownloadEntriesService;
use Illuminate\Http\Request;
use ec5\Models\Eloquent\ProjectStructure;
use Auth;
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

    public function uploadTemplate(Request $request, RuleUploadTemplate $validator)
    {
        $projectId = $this->requestedProject->getId();
        $projectSlug = $this->requestedProject->slug;
        $projectStructure = ProjectStructure::where('project_id', $projectId)->first();
        $projectMappings = json_decode($projectStructure->project_mapping);
        $projectDefinition = json_decode($projectStructure->project_definition);
        $params = $request->all();
        $locationType = config('epicollect.strings.inputs_type.location');
        $groupType = config('epicollect.strings.inputs_type.group');
        $cookieName = config('epicollect.strings.cookies.download-entries');

        //todo validation request
        $validator->validate($params);
        if ($validator->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $validator->errors());
        }

        //we send a "media-request" parameter in the query string with a timestamp. to generate a cookie with the same timestamp
        $timestamp = $request->query($cookieName);
        if ($timestamp) {
            //check if the timestamp is valid
            if (!Common::isValidTimestamp($timestamp) && strlen($timestamp) === 13) {
                abort(404); //so it goes to an error page
            }
        } else {
            //error no timestamp was passed
            abort(404); //s it goes to an error page
        }

        $mapIndex = $params['map_index'];
        $formIndex = $params['form_index'];
        $branchRef = $params['branch_ref'];
        $formRef = $projectDefinition->project->forms[$formIndex]->ref;
        $formName = $projectDefinition->project->forms[$formIndex]->name;

        $mapTos = [];
        $mapName = $projectMappings[$mapIndex]->name;

        //are we looking for a branch template?
        if ($branchRef !== '') {
            $branchIndex = 0;
            $inputs = $projectDefinition->project->forms[$formIndex]->inputs;

            //find the branch inputs
            $branchFound = false;
            foreach ($inputs as $inputIndex => $input) {
                if ($input->ref === $branchRef) {
                    $inputs = $input->branch;
                    $branchIndex = $inputIndex;
                    $branchFound = true;
                    break;
                }
            }

            //if the branch id not found return error
            if (!$branchFound) {
                return $this->apiResponse->errorResponse(400, ['upload-template' => ['ec5_99']]);
            }

            $selectedMapping = $projectMappings[$mapIndex]->forms->{$formRef}->{$branchRef}->branch;
            $branchName = $projectDefinition->project->forms[$formIndex]->inputs[$branchIndex]->question;
            //truncate (and slugify) branch name to avoid super long file names
            $branchNameTruncated = Str::slug(substr(strtolower($branchName), 0, 100));

            $mapTos[] = 'ec5_branch_uuid';
            $filename = $projectSlug . '__' . $formName . '__' . $branchNameTruncated . '__' . $mapName . '__upload-template.csv';
        } else {
            //hierarchy template
            $inputs = $projectDefinition->project->forms[$formIndex]->inputs;
            $selectedMapping = $projectMappings[$mapIndex]->forms->{$formRef};
            $mapTos[] = 'ec5_uuid';
            $filename = $projectSlug . '__' . $formName . '__' . $mapName . '__upload-template.csv';
        }

        //get csv headers in order
        $csvHeaders = $this->getCSVHeaders(
            $inputs,
            $locationType,
            $selectedMapping,
            $groupType,
            $mapTos[]
        );

        //"If set to 0, or omitted, the cookie will expire at the end of the session (when the browser closes)."
        $mediaCookie = Cookie::make($cookieName, $timestamp, 0, null, null, false, false);
        Cookie::queue($mediaCookie);

        $content = implode(',', $csvHeaders);
        //return a csv file with the proper column headers
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        return response()->make($content, 200, $headers);
    }

    public function uploadHeaders(Request $request, RuleUploadHeaders $ruleUploadHeaders)
    {
        $projectId = $this->requestedProject->getId();
        $projectStructure = ProjectStructure::where('project_id', $projectId)->first();
        $projectMappings = json_decode($projectStructure->project_mapping);
        $projectDefinition = json_decode($projectStructure->project_definition);
        $params = $request->all();
        $locationType = config('epicollect.strings.inputs_type.location');
        $groupType = config('epicollect.strings.inputs_type.group');

        //validate request
        $ruleUploadHeaders->validate($params);
        if ($ruleUploadHeaders->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $ruleUploadHeaders->errors());
        }

        $mapIndex = $params['map_index'];
        $formIndex = $params['form_index'];
        $branchRef = $params['branch_ref'];
        $formRef = $projectDefinition->project->forms[$formIndex]->ref;

        $mapTos = [];

        //are we looking for a branch template?
        if ($branchRef !== '') {
            $inputs = $projectDefinition->project->forms[$formIndex]->inputs;

            //find the branch inputs
            $branchFound = false;
            foreach ($inputs as $input) {
                if ($input->ref === $branchRef) {
                    $inputs = $input->branch;
                    $branchFound = true;
                    break;
                }
            }

            //if the branch id not found return error
            if (!$branchFound) {
                return $this->apiResponse->errorResponse(400, ['upload-template' => ['ec5_99']]);
            }

            $selectedMapping = $projectMappings[$mapIndex]->forms->{$formRef}->{$branchRef}->branch;

            $mapTos[] = 'ec5_branch_uuid';
        } else {
            //hierarchy template
            $inputs = $projectDefinition->project->forms[$formIndex]->inputs;
            $selectedMapping = $projectMappings[$mapIndex]->forms->{$formRef};
            $mapTos[] = 'ec5_uuid';
        }

        //get csv headers in order
        $csvHeaders = $this->getCSVHeaders(
            $inputs,
            $locationType,
            $selectedMapping,
            $groupType,
            $mapTos);

        $content = ['headers' => $csvHeaders];
        //return json with the proper column headers
        return response()->apiResponse($content);
    }

    public function subset(Request $request, RuleDownload $ruleDownload)
    {
        $user = Auth::user();
        if ($user === null) {
            return $this->apiResponse->errorResponse(400, ['download-subset' => ['ec5_86']]);
        }

        $params = $this->getRequestParams($request, config('epicollect.limits.entries_table.per_page_download'));
        $cookieName = config('epicollect.strings.cookies.download-entries');
        // Validate request params
        $ruleDownload->validate($params);
        if ($this->ruleQueryString->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $this->ruleQueryString->errors());
        }

        //we send a "media-request" parameter in the query string with a timestamp. to generate a cookie with the same timestamp
        $timestamp = $request->query($cookieName);
        if ($timestamp) {
            //check if the timestamp is valid
            if (!Common::isValidTimestamp($timestamp) && strlen($timestamp) === 13) {
                abort(404); //so it goes to an error page
            }
        } else {
            //error no timestamp was passed
            abort(404); //s it goes to an error page
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

        $downloadEntriesService->create($this->requestedProject, $projectDir, $params);
        if ($downloadEntriesService->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $downloadEntriesService->errors());
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

    private function getCSVHeaders($inputs, $locationType, $selectedMapping, $groupType, $mapTos): array
    {
        $bulkUploadables = array_keys(config('epicollect.strings.bulk_uploadables'));
        $csvHeaders = $mapTos;
        foreach ($inputs as $input) {
            $inputRef = $input->ref;
            //only use question types bulk-uploadable
            if (in_array($input->type, $bulkUploadables)) {
                //need to split location in its parts (no UTM for now)
                if ($input->type === $locationType) {
                    $csvHeaders[] = 'lat_' . $selectedMapping->{$inputRef}->map_to;
                    $csvHeaders[] = 'long_' . $selectedMapping->{$inputRef}->map_to;
                    $csvHeaders[] = 'accuracy_' . $selectedMapping->{$inputRef}->map_to;
                } else {
                    //if the input is a group, flatten the group inputs
                    if ($input->type === $groupType) {

                        foreach ($input->group as $groupInput) {

                            $groupInputRef = $groupInput->ref;
                            if (in_array($groupInput->type, $bulkUploadables)) {
                                if ($groupInput->type === $locationType) {
                                    $csvHeaders[] = 'lat_' . $selectedMapping->{$inputRef}->group->{$groupInputRef}->map_to;
                                    $csvHeaders[] = 'long_' . $selectedMapping->{$inputRef}->group->{$groupInputRef}->map_to;
                                    $csvHeaders[] = 'accuracy_' . $selectedMapping->{$inputRef}->group->{$groupInputRef}->map_to;
                                } else {
                                    $csvHeaders[] = $selectedMapping->{$inputRef}->group->{$groupInputRef}->map_to;
                                }
                            }
                        }
                    } else {
                        $csvHeaders[] = $selectedMapping->{$inputRef}->map_to;
                    }
                }
            }
        }
        return $csvHeaders;
    }
}
