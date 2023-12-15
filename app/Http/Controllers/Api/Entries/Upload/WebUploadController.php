<?php

declare(strict_types=1);

namespace ec5\Http\Controllers\Api\Entries\Upload;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ec5\Http\Validation\Entries\Upload\RuleUpload;
use ec5\Http\Validation\Entries\Upload\RuleFileEntry as FileValidator;
use ec5\Repositories\QueryBuilder\Entry\Upload\Create\EntryRepository as EntryCreateRepository;
use ec5\Repositories\QueryBuilder\Entry\Upload\Create\BranchEntryRepository as BranchEntryCreateRepository;
use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\Api\ApiRequest;
use ec5\Models\Entries\EntryStructure;
use ec5\Models\Eloquent\Entry;
use ec5\Models\Eloquent\BranchEntry;
use ec5\Libraries\Utilities\DateFormatConverter;
use Illuminate\Http\Request;
use Storage;
use File;

class WebUploadController extends UploadControllerBase
{
    /*
    |--------------------------------------------------------------------------
    | Web Entry Upload Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the web upload of entry data
    |
    */

    /**
     * @var FileValidator
     */
    protected $fileValidator;

    /**
     * WebUploadController constructor.
     * @param Request $request
     * @param ApiRequest $apiRequest
     * @param ApiResponse $apiResponse
     * @param EntryStructure $entryStructure
     * @param EntryCreateRepository $entryCreateRepository
     * @param BranchEntryCreateRepository $branchEntryCreateRepository
     * @param FileValidator $fileValidator
     */
    public function __construct(
        Request                     $request,
        ApiRequest                  $apiRequest,
        ApiResponse                 $apiResponse,
        EntryStructure              $entryStructure,
        EntryCreateRepository       $entryCreateRepository,
        BranchEntryCreateRepository $branchEntryCreateRepository,
        FileValidator               $fileValidator
    )
    {
        $this->fileValidator = $fileValidator;
        parent::__construct(
            $request,
            $apiRequest,
            $apiResponse,
            $entryStructure,
            $entryCreateRepository,
            $branchEntryCreateRepository
        );
    }

    /**
     *
     */
    public function store(RuleUpload $ruleUpload)
    {
        //check the request is valid
        if (!$this->upload($ruleUpload)) {
            return $this->apiResponse->errorResponse(400, $this->errors);
        }

        //default response code for new entry
        $responseCode = 'ec5_237';
        $data = $this->apiRequest->getData();
        $projectId = $this->requestedProject->getId();

        //was an entry or branch entry upload?
        $uuid = $data['id'];

        if ($this->entryStructure->isBranch()) {
            $created_at = $data['branch_entry']['created_at'];
            $entry = BranchEntry::where('project_id', $projectId)->where('uuid', $uuid)->first();
        } else {
            $created_at = $data['entry']['created_at'];
            //get the entry we just saved
            $entry = Entry::where('project_id', $projectId)->where('uuid', $uuid)->first();
        }

        //if created_at matches, it was a newly created entry, otherwise an update
        // (the created_at in the database is not touched when updating an entry, only the uploaded_at)
        if (!DateFormatConverter::areTimestampsEqual($created_at, $entry->created_at)) {
            $responseCode = 'ec5_357';
        }

        /* MOVE FILES */
        $projectExtra = $this->requestedProject->getProjectExtra();
        $formRef = $this->entryStructure->getFormRef();

        if (!$this->entryStructure->isBranch()) {
            $inputs = $projectExtra->getFormInputs($formRef);
        } else {
            $inputs = $projectExtra->getBranchInputs($formRef, $this->entryStructure->getOwnerInputRef());
        }

        $disk = Storage::disk('temp');
        $rootFolder = $disk->getDriver()->getAdapter()->getPathPrefix();

        // Get all media for this particular entry by looping the inputs
        foreach ($inputs as $inputRef) {

            $input = $projectExtra->getInputData($inputRef);

            // If we have a group
            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                // Loop the group inputs
                $groupInputs = $projectExtra->getGroupInputs($formRef, $inputRef);
                foreach ($groupInputs as $groupInputRef) {
                    $groupInput = $projectExtra->getInputData($groupInputRef);
                    $this->moveFile($rootFolder, $groupInput);
                    if (count($this->errors) > 0) {
                        return $this->apiResponse->errorResponse(400, $this->errors);
                    }
                }
            } else {
                $this->moveFile($rootFolder, $input);
                if (count($this->errors) > 0) {
                    return $this->apiResponse->errorResponse(400, $this->errors);
                }
            }
        }

        //Throttle for half a second so the server does not get smashed by uploads
        time_nanosleep(0, (int)(config('epicollect.setup.api.response_delay.upload')));


        /* PASSED */
        // Send http status code 200, ok!
        return $this->apiResponse->successResponse($responseCode);
    }

    /**
     * @param RuleUpload $ruleUpload
     *
     * Let's call the web upload controller @store method
     * We do this because the @storeBulk endpoint goes through
     * a middleware to check for bulk upload permissions
     */
    public function storeBulk(RuleUpload $ruleUpload)
    {
        $this->isBulkUpload = true;
        return $this->store($ruleUpload);
    }

    /**
     * @param $rootFolder
     * @param $input
     * @return bool|JsonResponse
     */
    private function moveFile($rootFolder, $input)
    {
        // If we don't have a media input type
        if (!in_array($input['type'], array_keys(config('epicollect.strings.media_input_types')))) {
            return false;
        }

        $fileName = $this->entryStructure->getValidatedAnswer($input['ref'])['answer'];
        $filePath = $rootFolder . $input['type'] . '/' . $this->requestedProject->ref . '/' . $fileName;

        // If the answer is empty
        // Or if we don't have a file for this input in the temp folder
        if (empty($fileName) || !File::exists($filePath)) {
            return false;
        }

        try {
            $file = new UploadedFile(
                $filePath,
                $fileName,
                mime_content_type($filePath),
                filesize($filePath)
            );
        } catch (Exception $e) {
            // File doesn't exist
            return $this->apiResponse->errorResponse(400, ['web upload' => [
                'ec5_231'
            ]]);
        }

        // Load everything into an entry structure model
        $entryStructure = new EntryStructure();

        $entryData = config('epicollect.structures.entry_data');
        $entryData['id'] = $this->entryStructure->getEntryUuid();
        $entryData['type'] = array_keys(config('epicollect.strings.entry_types.file_entry'));
        $entryData[$entryData['type']] = [
            'type' => $input['type'],
            'name' => $fileName,
            'input_ref' => $input['ref']
        ];

        $entryStructure->createStructure($entryData);
        $entryStructure->setFile($file);

        // Move file
        // Note: the file has already been validated on initial upload to temp folder
        $this->fileValidator->moveFile($this->requestedProject, $entryStructure);
        if ($this->fileValidator->hasErrors()) {
            $this->errors = $this->fileValidator->errors();
            return false;
        }

        // Delete file from temp folder
        File::delete($filePath);

        return true;
    }
}
