<?php

namespace ec5\Http\Controllers\Api\Entries;

use ec5\Http\Controllers\Api\ApiRequest;
use ec5\Http\Controllers\Api\ApiResponse;

use ec5\Http\Controllers\Api\Entries\View\EntrySearchControllerBase;
use ec5\Http\Validation\Entries\Upload\RuleAnswers;
use ec5\Http\Validation\Entries\Search\RuleQueryString;
use ec5\Http\Validation\Entries\Download\RuleDownload as DownloadValidator;

use ec5\Repositories\QueryBuilder\Entry\Search\BranchEntryRepository;
use ec5\Repositories\QueryBuilder\Entry\Search\EntryRepository;
use ec5\Repositories\QueryBuilder\Entry\ToFile\CreateRepository as FileCreateRepository;
use ec5\Http\Validation\Entries\Upload\RuleUploadTemplate;
use ec5\Http\Validation\Entries\Upload\RuleUploadHeaders;

use Illuminate\Http\Request;

use ec5\Models\Eloquent\ProjectStructure;

use Auth;
use Config;
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

    /**
     * @var FileCreateRepository
     */
    protected $fileCreateRepository;

    /**
     * @var
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
     * @param FileCreateRepository $fileCreateRepository
     */
    public function __construct(
        Request $request,
        ApiRequest $apiRequest,
        ApiResponse $apiResponse,
        EntryRepository $entryRepository,
        BranchEntryRepository $branchEntryRepository,
        RuleQueryString $ruleQueryString,
        RuleAnswers $ruleAnswers,
        FileCreateRepository $fileCreateRepository

    ) {
        parent::__construct(
            $request,
            $apiRequest,
            $apiResponse,
            $entryRepository,
            $branchEntryRepository,
            $ruleQueryString,
            $ruleAnswers
        );

        $this->allowedSearchKeys = Config::get('ec5Enums.download_data_entries');
        $this->fileCreateRepository = $fileCreateRepository;
    }

    /**
     * @param Request $request
     * @param DownloadValidator $downloadValidator
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function index(Request $request, DownloadValidator $downloadValidator)
    {
        $user = Auth::user();
        $options = $this->getRequestOptions($request, Config::get('ec5Limits.entries_table.per_page_download'));

        $cookieName = Config::get('ec5Strings.cookies.download-entries');

        if ($user === null) {
            return $this->apiResponse->errorResponse(400, ['download-entries' => ['ec5_86']]);
        }

        // Validate the options
        $downloadValidator->validate($options);
        if ($this->ruleQueryString->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $this->ruleQueryString->errors());
        }

        //todo do this better and use a form request maybe
        //we send a "media-request" parameter in the query tring with a timestamp. to generate a cookie with the same timestamp
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

        // Default format if not supplied
        if (!isset($options['format']) || empty($options['format'])) {
            $options['format'] = Config::get('ec5Enums.download_data_entries_format_default');
        }

        // Setup storage
        $storage = Storage::disk('entries_zip');
        // Check storage error here
        if ($storage == null) {
            $this->errors = ['download' => ['ec5_21']];
            return $this->apiResponse->errorResponse(400, $this->errors);
        }
        $storagePrefix = (empty($storage)) ? '' : $storage->getDriver()->getAdapter()->getPathPrefix();

        //todo check if there is a zip file already, send it
        //todo it gets destroyed when we post entries returnZip

        $projectDir = $storagePrefix . $this->requestedProject->ref;
        //append user ID to handle concurrency -> MUST be logged in to download!
        $projectDir = $projectDir . '/' . $user->id;

        // Try and create the files
        $this->fileCreateRepository->create($this->requestedProject, $projectDir, $options);
        if ($this->fileCreateRepository->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $this->fileCreateRepository->errors());
        }

        $zipName = $this->requestedProject->slug . '-' . $options['format'] . '.zip';
        return $this->returnZip($projectDir . '/' . $zipName, $zipName, $timestamp);
    }

    public function uploadTemplate(Request $request, RuleUploadTemplate $validator)
    {
        $projectId = $this->requestedProject->getId();
        $projectSlug = $this->requestedProject->slug;
        $projectStructure = ProjectStructure::where('project_id', $projectId)->first();
        $projectMappings = json_decode($projectStructure->project_mapping);
        $projectDefinition = json_decode($projectStructure->project_definition);
        $params = $request->all();
        $readmeType = Config::get('ec5Strings.inputs_type.readme');
        $locationType = Config::get('ec5Strings.inputs_type.location');
        $groupType = Config::get('ec5Strings.inputs_type.group');
        $cookieName = Config::get('ec5Strings.cookies.download-entries');

        //todo validation request
        $validator->validate($params);
        if ($validator->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $validator->errors());
        }

        //we send a "media-request" parameter in the query tring with a timestamp. to generate a cookie with the same timestamp
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
        $bulkUploadables = Config::get('ec5Enums.bulk_uploadables');

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

        //loop inputs in order
        foreach ($inputs as $input) {

            $inputRef = $input->ref;
            //only use question types bulk-uploadable
            if (in_array($input->type, $bulkUploadables)) {
                //need to split location in its parts (no UTM for now)
                if ($input->type === $locationType) {
                    $mapTos[] = 'lat_' . $selectedMapping->{$inputRef}->map_to;
                    $mapTos[] = 'long_' . $selectedMapping->{$inputRef}->map_to;
                    $mapTos[] = 'accuracy_' . $selectedMapping->{$inputRef}->map_to;
                } else {
                    //if the input is a group, flatten the group inputs
                    if ($input->type === $groupType) {

                        foreach ($input->group as $groupInput) {

                            $groupInputRef = $groupInput->ref;
                            if (in_array($groupInput->type, $bulkUploadables)) {
                                if ($groupInput->type === $locationType) {
                                    $mapTos[] = 'lat_' . $selectedMapping->{$inputRef}->group->{$groupInputRef}->map_to;
                                    $mapTos[] = 'long_' . $selectedMapping->{$inputRef}->group->{$groupInputRef}->map_to;
                                    $mapTos[] = 'accuracy_' . $selectedMapping->{$inputRef}->group->{$groupInputRef}->map_to;
                                } else {
                                    $mapTos[] = $selectedMapping->{$inputRef}->group->{$groupInputRef}->map_to;
                                }
                            }
                        }
                    } else {
                        $mapTos[] = $selectedMapping->{$inputRef}->map_to;
                    }
                }
            }
        }

        //"If set to 0, or omitted, the cookie will expire at the end of the session (when the browser closes)."
        $mediaCookie = Cookie::make($cookieName, $timestamp, 0, null, null, false, false);
        Cookie::queue($mediaCookie);

        $content = implode(',', $mapTos);
        //return a csv file with the proper column headers
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        return response()->make($content, 200, $headers);
    }

    public function uploadHeaders(Request $request, RuleUploadHeaders $validator)
    {
        $projectId = $this->requestedProject->getId();
        $projectStructure = ProjectStructure::where('project_id', $projectId)->first();
        $projectMappings = json_decode($projectStructure->project_mapping);
        $projectDefinition = json_decode($projectStructure->project_definition);
        $params = $request->all();
        $readmeType = Config::get('ec5Strings.inputs_type.readme');
        $locationType = Config::get('ec5Strings.inputs_type.location');
        $groupType = Config::get('ec5Strings.inputs_type.group');

        //todo validation request
        $validator->validate($params);
        if ($validator->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $validator->errors());
        }

        $mapIndex = $params['map_index'];
        $formIndex = $params['form_index'];
        $branchRef = $params['branch_ref'];
        $formRef = $projectDefinition->project->forms[$formIndex]->ref;

        $mapTos = [];
        $bulkUploadables = Config::get('ec5Enums.bulk_uploadables');

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

            $mapTos[] = 'ec5_branch_uuid';
        } else {
            //hierarchy template
            $inputs = $projectDefinition->project->forms[$formIndex]->inputs;
            $selectedMapping = $projectMappings[$mapIndex]->forms->{$formRef};
            $mapTos[] = 'ec5_uuid';
        }

        //loop inputs in order
        foreach ($inputs as $input) {

            $inputRef = $input->ref;
            //only use question types bulk-uploadable
            if (in_array($input->type, $bulkUploadables)) {
                //need to split location in its parts (no UTM for now)
                if ($input->type === $locationType) {
                    $mapTos[] = 'lat_' . $selectedMapping->{$inputRef}->map_to;
                    $mapTos[] = 'long_' . $selectedMapping->{$inputRef}->map_to;
                    $mapTos[] = 'accuracy_' . $selectedMapping->{$inputRef}->map_to;
                } else {
                    //if the input is a group, flatten the group inputs
                    if ($input->type === $groupType) {

                        foreach ($input->group as $groupInput) {

                            $groupInputRef = $groupInput->ref;
                            if (in_array($groupInput->type, $bulkUploadables)) {
                                if ($groupInput->type === $locationType) {
                                    $mapTos[] = 'lat_' . $selectedMapping->{$inputRef}->group->{$groupInputRef}->map_to;
                                    $mapTos[] = 'long_' . $selectedMapping->{$inputRef}->group->{$groupInputRef}->map_to;
                                    $mapTos[] = 'accuracy_' . $selectedMapping->{$inputRef}->group->{$groupInputRef}->map_to;
                                } else {
                                    $mapTos[] = $selectedMapping->{$inputRef}->group->{$groupInputRef}->map_to;
                                }
                            }
                        }
                    } else {
                        $mapTos[] = $selectedMapping->{$inputRef}->map_to;
                    }
                }
            }
        }

        $content = ['headers' => $mapTos];
        //return json with the proper column headers

        return response()->apiResponse($content);
    }

    public function subset(Request $request, DownloadValidator $downloadValidator)
    {

        $options = $this->getRequestOptions($request, Config::get('ec5Limits.entries_table.per_page_download'));

        $cookieName = Config::get('ec5Strings.cookies.download-entries');

        // Validate the options
        $downloadValidator->validate($options);
        if ($this->ruleQueryString->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $this->ruleQueryString->errors());
        }

        //todo do this better and use a form request maybe
        //we send a "media-request" parameter in the query tring with a timestamp. to generate a cookie with the same timestamp
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

        // Default format if not supplied
        if (!isset($options['format']) || empty($options['format'])) {
            $options['format'] = Config::get('ec5Enums.download_data_entries_format_default');
        }

        // Setup storage
        $storage = Storage::disk('entries_zip');
        // Check storage error here
        if ($storage == null) {
            $this->errors = ['download' => ['ec5_21']];
            return $this->apiResponse->errorResponse(400, $this->errors);
        }
        $storagePrefix = (empty($storage)) ? '' : $storage->getDriver()->getAdapter()->getPathPrefix();

        //todo check if there is a zip file already, send it
        //todo it gets destroyed when we post entries returnZip

        $projectDir = $storagePrefix . $this->requestedProject->ref;


        // Try and create the files
        $this->fileCreateRepository->create($this->requestedProject, $projectDir, $options);
        if ($this->fileCreateRepository->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $this->fileCreateRepository->errors());
        }

        $zipName = $this->requestedProject->slug . '-' . $options['format'] . '.zip';

        return $this->returnZip($projectDir . '/' . $zipName, $zipName, $timestamp);
    }

    /**
     * @param $filepath
     * @param $filename
     * @param null $timestamp
     */
    private function returnZip($filepath, $filename, $timestamp = null)
    {
        $cookieName = Config::get('ec5Strings.cookies.download-entries');

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
}
