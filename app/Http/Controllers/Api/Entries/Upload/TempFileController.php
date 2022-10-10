<?php

declare(strict_types=1);

namespace ec5\Http\Controllers\Api\Entries\Upload;

use ec5\Libraries\EC5Logger\EC5Logger;

use ec5\Http\Validation\Entries\Upload\RuleFileEntry as FileValidator;
use ec5\Http\Validation\Media\RuleTempMediaDelete as TempMediaDeleteValidator;
use ec5\Repositories\QueryBuilder\Stats\Entry\StatsRepository as EntryStatsRepository;
use ec5\Repositories\QueryBuilder\Entry\Upload\Create\EntryRepository as EntryCreateRepository;
use ec5\Repositories\QueryBuilder\Entry\Upload\Create\BranchEntryRepository as BranchEntryCreateRepository;
use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\Api\ApiRequest;
use ec5\Models\Entries\EntryStructure;
use Illuminate\Http\Request;

use Storage;
use App;
use Exception;
use File;

class TempFileController extends UploadControllerBase
{

    /**
     * @var FileValidator
     */
    protected $fileValidator;
    protected $tempMediaDeleteValidator;

    public function __construct(
        Request $request,
        ApiRequest $apiRequest,
        ApiResponse $apiResponse,
        EntryStructure $entryStructure,
        EntryCreateRepository $entryCreateRepository,
        BranchEntryCreateRepository $branchEntryCreateRepository,
        FileValidator $fileValidator,
        TempMediaDeleteValidator $tempMediaDeleteValidator,
        EntryStatsRepository $entryStatsRepository
    ) {
        $this->fileValidator = $fileValidator;
        $this->tempMediaDeleteValidator = $tempMediaDeleteValidator;
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
     * Handle a web temp file upload entry request
     */
    public function store()
    {
        /* API REQUEST VALIDATION */
        if (!$this->isValidApiRequest()) {
            return $this->apiResponse->errorResponse(400, $this->errors);
        }

        /* CHECK PROJECT STATUS */
        if (!$this->hasValidProjectStatus()) {
            return $this->apiResponse->errorResponse(400, $this->errors);
        }

        /* USER AUTHENTICATION AND PERMISSIONS CHECK FOR PRIVATE PROJECTS */
        if (!$this->userHasPermissions()) {
            return $this->apiResponse->errorResponse(400, $this->errors);
        }

        /* BUILD ENTRY STRUCTURE */
        $this->buildEntryStructure();
        $file = $this->request->file('file');
        if (!$file) {
            return $this->apiResponse->errorResponse(400, ['temp file upload' => ['ec5_116']]);
        }
        $this->entryStructure->setFile($file);

        /* VALIDATE */

        if (!$this->fileValidator->fileInputExists($this->requestedProject, $this->entryStructure)) {
            return $this->apiResponse->errorResponse(400, $this->fileValidator->errors());
        }
        // Validate web media file
        if (!$this->fileValidator->isValidFile($this->entryStructure, true)) {
            return $this->apiResponse->errorResponse(400, $this->fileValidator->errors());
        }

        /* STORE FILE */
        $projectRef = $this->requestedProject->ref;

        // Get the entry data
        $fileEntry = $this->entryStructure->getEntry();
        $fileType = $fileEntry['type'];
        $fileName = $fileEntry['name'];
        $inputRef = $fileEntry['input_ref'];

        // Store the file into storage location, using driver based on the file type
        $fileSaved = Storage::disk('temp')->put(
            $fileType . '/' . $projectRef . '/' . $fileName,
            file_get_contents($file->getRealPath())
        );

        // Check if put was successful
        if (!$fileSaved) {
            $this->errors[$inputRef] = ['ec5_83'];
            EC5Logger::error('Temp media file could not be saved', $this->requestedProject, $this->errors);
            return $this->apiResponse->errorResponse(400, $this->errors);
        }

        /* PASSED */
        // Send http status code 200, ok!
        return $this->apiResponse->successResponse('ec5_242');
    }

    //delete a temp file (PWA)
    public function destroy()
    {
        /* API REQUEST VALIDATION */
        if (!$this->isValidApiRequest()) {
            return $this->apiResponse->errorResponse(400, $this->errors);
        }

        $projectRef = $this->requestedProject->ref;
        $data = $this->apiRequest->getData();

        \Log::info('delete file request', ['data' => $data]);

        $this->tempMediaDeleteValidator->validate($data);
        if ($this->tempMediaDeleteValidator->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $this->tempMediaDeleteValidator->errors);
        }

        $this->tempMediaDeleteValidator->additionalChecks($data);
        if ($this->tempMediaDeleteValidator->hasErrors()) {
            return $this->apiResponse->errorResponse(400, $this->tempMediaDeleteValidator->errors);
        }

        // Get a handle to the temp/ folder
        $disk = Storage::disk('temp');
        $rootFolder = $disk->getDriver()->getAdapter()->getPathPrefix();

        // Delete file from temp folder
        $filetype = $data['delete']['filetype'];
        $filename = $data['delete']['filename'];
        $filePath = $rootFolder .  $filetype . '/' . $projectRef . '/' . $filename;

        try {
            File::delete($filePath);
            // File successfully deleted 
            return $this->apiResponse->successResponse('ec5_122');
        } catch (Exception $e) {
            //error deleting file
            return $this->apiResponse->errorResponse(400, ['temp-media-delete' => ['ec5_103']]);
        }
    }

    //used for debugging PWA
    public function storePWA()
    {
        if (!App::isLocal()) {
            return $this->apiResponse->errorResponse(400, ['pwa-file-upload' => ['ec5_91']]);
        }
        return $this->store();
    }

    //used for debugging PWA
    public function destroyPWA()
    {
        if (!App::isLocal()) {
            return $this->apiResponse->errorResponse(400, ['pwa-file-delete' => ['ec5_91']]);
        }

        return $this->destroy();
    }
}
