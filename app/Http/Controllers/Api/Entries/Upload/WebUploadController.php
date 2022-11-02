<?php

declare(strict_types=1);

namespace ec5\Http\Controllers\Api\Entries\Upload;

use ec5\Libraries\EC5Logger\EC5Logger;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use ec5\Http\Validation\Entries\Upload\RuleUpload as UploadValidator;
use ec5\Http\Validation\Entries\Upload\RuleFileEntry as FileValidator;
use ec5\Repositories\QueryBuilder\Stats\Entry\StatsRepository as EntryStatsRepository;

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
use Config;
use File;
use App;
use Exception;
use Log;

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
     * @param EntryStatsRepository $entryStatsRepository
     */
    public function __construct(
        Request $request,
        ApiRequest $apiRequest,
        ApiResponse $apiResponse,
        EntryStructure $entryStructure,
        EntryCreateRepository $entryCreateRepository,
        BranchEntryCreateRepository $branchEntryCreateRepository,
        FileValidator $fileValidator,
        EntryStatsRepository $entryStatsRepository
    ) {
        $this->fileValidator = $fileValidator;
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
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function store(UploadValidator $uploadValidator)
    {
        //check the request is valid
        if (!$this->upload($uploadValidator)) {
            return $this->apiResponse->errorResponse(400, $this->errors);
        }

        //default response code for new entry
        $responseCode = 'ec5_237';
        $data = $this->apiRequest->getData();
        $projectId = $this->requestedProject->getId();

        \Log::info('uploaded data', ['data' => $data]);


        //was an entry or branch entry upload?
        $uuid = $data['id'];


        if ($this->entryStructure->isBranch()) {
            $entryType = 'branch_entry';
            $created_at = $data['branch_entry']['created_at'];
            $entry = BranchEntry::where('project_id', $projectId)->where('uuid', $uuid)->first();
        } else {
            $entryType = 'entry';
            $created_at = $data['entry']['created_at'];
            //get the entry we just saved
            $entry = Entry::where('project_id', $projectId)->where('uuid', $uuid)->first();
        }

        //if created_at matches, it was a newly created entry, otherwise an update
        // (the created_at in the database is not touched when updating an entry, only the uploaded_at)
        if (!DateFormatConverter::areTimestampsEqual($created_at, $entry->created_at)) {
            $responseCode = 'ec5_357';
            //existing entries when edited could have some stored files removed
            try {
                if (array_key_exists('files_to_delete', $data['entry'])) {
                    $filesToDelete = $data['entry']['files_to_delete'];
                    //any files to delete from permanent storage before we move the temp ones?
                    if (count($filesToDelete) > 0) {
                        $this->deleteStoredFiles($filesToDelete);
                    }
                }
            } catch (Exception $e) {
                //todo: handle error
                Log::error($e->getMessage());
            }
        }

        // MOVE FILES from temp/ to app/entries/{media folder}
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
            if ($input['type'] === Config::get('ec5Strings.inputs_type.group')) {
                // Loop the group inputs
                $groupInputs = $projectExtra->getGroupInputs($formRef, $inputRef);
                foreach ($groupInputs as $groupInputRef) {
                    $groupInput = $projectExtra->getInputData($groupInputRef);
                    $this->moveFile($rootFolder, $groupInput);
                    if (count($this->errors) > 0) {
                        EC5Logger::error('Could not move group file', $this->requestedProject, $this->errors);
                        return $this->apiResponse->errorResponse(400, $this->errors);
                    }
                }
            } else {
                $this->moveFile($rootFolder, $input);
                if (count($this->errors) > 0) {
                    EC5Logger::error('Could not move file', $this->requestedProject, $this->errors);
                    return $this->apiResponse->errorResponse(400, $this->errors);
                }
            }
        }

        //Throttle for half a second so the server does not get smashed by uploads
        time_nanosleep(0, (int)(env('RESPONSE_DELAY_UPLOAD_REQUEST', 500000000)));


        /* PASSED */
        // Send http status code 200, ok!
        return $this->apiResponse->successResponse($responseCode);
    }

    public function storePWA(UploadValidator $uploadValidator)
    {
        //kick out if in production, this route is only for debugging locally
        if (!App::isLocal()) {
            return $this->apiResponse->errorResponse(400, ['pwa-upload' => ['ec5_91']]);
        }
        return $this->store($uploadValidator);
    }

    /**
     * @param UploadValidator $uploadValidator
     * @return bool|\Illuminate\Http\JsonResponse
     *
     * Let's call the web upload controller @store method
     * We do this because the @storeBulk endpoint goes through
     * a middleware to check for bulk upload permissions
     */
    public function storeBulk(UploadValidator $uploadValidator)
    {
        $this->isBulkUpload = true;
        return $this->store($uploadValidator);
    }

    /**
     * @param $rootFolder
     * @param $input
     * @return bool|\Illuminate\Http\JsonResponse
     */
    private function moveFile($rootFolder, $input)
    {
        // If we don't have a media input type
        if (!in_array($input['type'], Config::get('ec5Enums.media_input_types'))) {
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
        } catch (\ErrorException $e) {
            // File doesn't exist
            return $this->apiResponse->errorResponse(400, ['web upload' => ['ec5_231']]);
        }

        // Load everything into an entry structure model
        $entryStructure = new EntryStructure();

        $entryData = Config::get('ec5ProjectStructures.entry_data');
        $entryData['id'] = $this->entryStructure->getEntryUuid();
        $entryData['type'] = Config::get('ec5Strings.entry_types.file_entry');
        $entryData[Config::get('ec5Strings.entry_types.file_entry')] = [
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

    private function deleteStoredFiles($files)
    {
        foreach ($files as $file) {
            switch ($file['type']) {
                case config('ec5Strings.inputs_type.photo'):
                    Log::error('here 1');
                    $fileType = $file['type'];
                    $filename = $file['filename'];
                    //delete from photo folders (all sizes)
                    $disk = Storage::disk('entry_original');
                    $rootFolder = $disk->getDriver()->getAdapter()->getPathPrefix();
                    $filePath = $rootFolder  . $this->requestedProject->ref . '/' . $filename;
                    try {
                        // Delete file from entry_original folder
                        if (!File::delete($filePath)) {
                            Log::error('Could not delete ' .  $fileType . ' stored file ->' . $filename);
                        }
                    } catch (Exception $e) {
                        Log::error('Could not delete ' . $fileType . ' stored file ->' . $filename);
                    }

                    $disk = Storage::disk('entry_thumb');
                    $rootFolder = $disk->getDriver()->getAdapter()->getPathPrefix();
                    $filePath = $rootFolder . $this->requestedProject->ref . '/' . $filename;
                    Log::error('here 2 -> ' . $filePath);
                    try {
                        // Delete file from entry_original folder
                        if (!File::delete($filePath)) {
                            Log::error('Could not delete ' .  $fileType . ' stored file ->' . $filename);
                        }
                    } catch (Exception $e) {
                        Log::error('Could not delete ' . $fileType . ' stored file ->' . $filename);
                    }

                    $disk = Storage::disk('entry_sidebar');
                    $rootFolder = $disk->getDriver()->getAdapter()->getPathPrefix();
                    $filePath = $rootFolder . $this->requestedProject->ref . '/' . $filename;
                    try {
                        // Delete file from entry_original folder
                        if (!File::delete($filePath)) {
                            Log::error('Could not delete ' .  $fileType . ' stored file ->' . $filename);
                        }
                    } catch (Exception $e) {
                        Log::error('Could not delete ' . $fileType . ' stored file ->' . $filename);
                    }


                    break;
                case config('ec5Strings.inputs_type.audio'):
                    //delete from audio folder
                    //delete from video folder
                    $disk = Storage::disk('audio');
                    $fileType = $file['type'];
                    $filename = $file['filename'];
                    $rootFolder = $disk->getDriver()->getAdapter()->getPathPrefix();
                    $filePath = $rootFolder  . $this->requestedProject->ref . '/' . $filename;
                    try {
                        // Delete file from audio folder
                        if (!File::delete($filePath)) {
                            Log::error('Could not delete ' .  $fileType . ' stored file ->' . $filename);
                        }
                    } catch (Exception $e) {
                        Log::error('Could not delete ' . $fileType . ' stored file ->' . $filename);
                    }
                    break;
                case config('ec5Strings.inputs_type.video'):
                    //delete from video folder
                    $disk = Storage::disk('video');
                    $fileType = $file['type'];
                    $filename = $file['filename'];
                    $rootFolder = $disk->getDriver()->getAdapter()->getPathPrefix();
                    $filePath = $rootFolder  . $this->requestedProject->ref . '/' . $filename;
                    try {
                        // Delete file from video folder
                        if (!File::delete($filePath)) {
                            Log::error('Could not delete ' .  $fileType . ' stored file ->' . $filename);
                        }
                    } catch (Exception $e) {
                        Log::error('Could not delete ' . $fileType . ' stored file ->' . $filename);
                    }
                    break;
            }
        }
    }
}
