<?php

namespace ec5\Http\Controllers\Api\Entries;

use ec5\Http\Controllers\Api\ApiRequest;
use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\Api\Entries\View\EntrySearchControllerBase;
use ec5\Http\Validation\Entries\Upload\RuleAnswers;
use ec5\Http\Validation\Entries\Search\RuleQueryString;
use ec5\Repositories\QueryBuilder\Entry\Search\BranchEntryRepository;
use ec5\Repositories\QueryBuilder\Entry\Search\EntryRepository;
use ec5\Http\Validation\Entries\Upload\RuleDownloadTemplate;
use ec5\Http\Validation\Entries\Upload\RuleUploadHeaders;
use Illuminate\Http\Request;
use ec5\Models\Eloquent\ProjectStructure;
use Cookie;
use Illuminate\Support\Str;
use ec5\Libraries\Utilities\Common;

class DownloadTemplateController extends EntrySearchControllerBase
{
    /*
    |--------------------------------------------------------------------------
    | Download Bulk Uploads Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the downloading of bulk uploads template files or headers
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

    public function sendTemplateFileCSV(Request $request, RuleDownloadTemplate $ruleUploadTemplate)
    {
        $projectId = $this->requestedProject->getId();
        $projectSlug = $this->requestedProject->slug;
        $projectStructure = ProjectStructure::where('project_id', $projectId)->first();
        $projectMappings = json_decode($projectStructure->project_mapping, true);
        $projectDefinition = json_decode($projectStructure->project_definition, true);
        $params = $request->all();
        $locationType = config('epicollect.strings.inputs_type.location');
        $groupType = config('epicollect.strings.inputs_type.group');
        $cookieName = config('epicollect.strings.cookies.download-entries');

        //todo validation request
        $ruleUploadTemplate->validate($params);
        if ($ruleUploadTemplate->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $ruleUploadTemplate->errors());
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
        $formRef = $projectDefinition['project']['forms'][$formIndex]['ref'];
        $formName = $projectDefinition['project']['forms'][$formIndex]['name'];

        $mapTos = [];
        $mapName = $projectMappings[$mapIndex]['name'];

        //are we looking for a branch template?
        if ($branchRef !== '') {
            $branchIndex = 0;
            $inputs = $projectDefinition['project']['forms'][$formIndex]['inputs'];

            //find the branch inputs
            $branchFound = false;
            foreach ($inputs as $inputIndex => $input) {
                if ($input['ref'] === $branchRef) {
                    $inputs = $input['branch'];
                    $branchIndex = $inputIndex;
                    $branchFound = true;
                    break;
                }
            }

            //if the branch id not found return error
            if (!$branchFound) {
                return $this->apiResponse->errorResponse(400, ['upload-template' => ['ec5_99']]);
            }

            $selectedMapping = $projectMappings[$mapIndex]['forms'][$formRef][$branchRef]['branch'];
            $branchName = $projectDefinition['project']['forms'][$formIndex]['inputs'][$branchIndex]['question'];
            //truncate (and slugify) branch name to avoid super long file names
            $branchNameTruncated = Str::slug(substr(strtolower($branchName), 0, 100));
            $mapTos[] = 'ec5_branch_uuid';
            $filename = $projectSlug . '__' . $formName . '__' . $branchNameTruncated . '__' . $mapName . '__upload-template.csv';
        } else {
            //hierarchy template
            $inputs = $projectDefinition['project']['forms'][$formIndex]['inputs'];
            $selectedMapping = $projectMappings[$mapIndex]['forms'][$formRef];
            $mapTos[] = 'ec5_uuid';
            $filename = $projectSlug . '__' . $formName . '__' . $mapName . '__upload-template.csv';
        }

        //get csv headers in order
        $csvHeaders = Common::getTemplateHeaders(
            $inputs,
            $selectedMapping,
            $mapTos
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

    public function sendTemplateResponseJSON(Request $request, RuleUploadHeaders $ruleUploadHeaders)
    {
        $projectId = $this->requestedProject->getId();
        $projectStructure = ProjectStructure::where('project_id', $projectId)->first();
        $projectMappings = json_decode($projectStructure->project_mapping, true);
        $projectDefinition = json_decode($projectStructure->project_definition, true);
        $params = $request->all();

        //validate request
        $ruleUploadHeaders->validate($params);
        if ($ruleUploadHeaders->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $ruleUploadHeaders->errors());
        }

        $mapIndex = $params['map_index'];
        $formIndex = $params['form_index'];
        $branchRef = $params['branch_ref'];
        $formRef = $projectDefinition['project']['forms'][$formIndex]['ref'];
        $mapTos = [];
        //are we looking for a branch template?
        if ($branchRef !== '') {
            $inputs = $projectDefinition['project']['forms'][$formIndex]['inputs'];

            //find the branch inputs
            $branchFound = false;
            foreach ($inputs as $input) {
                if ($input['ref'] === $branchRef) {
                    $inputs = $input['branch'];
                    $branchFound = true;
                    break;
                }
            }

            //if the branch id not found return error
            if (!$branchFound) {
                return $this->apiResponse->errorResponse(400, ['upload-template' => ['ec5_99']]);
            }

            $selectedMapping = $projectMappings[$mapIndex]['forms'][$formRef][$branchRef]['branch'];

            $mapTos[] = 'ec5_branch_uuid';
        } else {
            //hierarchy template
            $inputs = $projectDefinition['project']['forms'][$formIndex]['inputs'];
            $selectedMapping = $projectMappings[$mapIndex]['forms'][$formRef];
            $mapTos[] = 'ec5_uuid';
        }

        //get csv headers in order
        $csvHeaders = Common::getTemplateHeaders(
            $inputs,
            $selectedMapping,
            $mapTos);

        $content = ['headers' => $csvHeaders];
        //return json with the proper column headers
        return response()->apiResponse($content);
    }

}