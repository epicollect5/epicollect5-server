<?php
declare(strict_types=1);

namespace ec5\Http\Controllers\Api\Entries\Upload;


use ec5\Http\Validation\Entries\Upload\RuleFileEntry as FileValidator;
use ec5\Repositories\QueryBuilder\Entry\Upload\Create\EntryRepository as EntryCreateRepository;
use ec5\Repositories\QueryBuilder\Entry\Upload\Create\BranchEntryRepository as BranchEntryCreateRepository;
use ec5\Http\Controllers\Api\ApiResponse;
use ec5\Http\Controllers\Api\ApiRequest;
use ec5\Models\Entries\EntryStructure;
use Illuminate\Http\Request;
use Storage;

class TempFileController extends UploadControllerBase
{

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
        parent::__construct($request, $apiRequest, $apiResponse, $entryStructure, $entryCreateRepository,
            $branchEntryCreateRepository);
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
        $file = request()->file('file');
        if (!$file) {
            return $this->apiResponse->errorResponse(400, ['temp file upload' => ['ec5_116']]);
        }
        $this->entryStructure->setFile($file);

        /* VALIDATE */

        if (!$this->fileValidator->fileInputExists($this->requestedProject(), $this->entryStructure)) {
            return $this->apiResponse->errorResponse(400, $this->fileValidator->errors());
        }
        // Validate web media file
        if (!$this->fileValidator->isValidFile($this->entryStructure, true)) {
            return $this->apiResponse->errorResponse(400, $this->fileValidator->errors());
        }

        /* STORE FILE */
        $projectRef = $this->requestedProject()->ref;

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
            return $this->apiResponse->errorResponse(400, $this->errors);
        }

        /* PASSED */
        // Send http status code 200, ok!
        return $this->apiResponse->successResponse('ec5_242');
    }
}
