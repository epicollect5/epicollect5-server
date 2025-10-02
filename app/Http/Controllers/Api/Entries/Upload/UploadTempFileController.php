<?php

namespace ec5\Http\Controllers\Api\Entries\Upload;

use ec5\Http\Validation\Entries\Upload\RuleFileEntry;
use Log;
use Response;
use Storage;
use Throwable;

class UploadTempFileController extends UploadControllerBase
{
    /**
     * Handle a web temp file upload entry request
     */
    public function store(RuleFileEntry $ruleFileEntry)
    {
        //is the project active?
        if (!$this->entriesUploadService->isProjectActive()) {
            return Response::apiErrorCode(400, ['temp-file-upload' => ['ec5_202']]);
        }
        /* USER AUTHENTICATION AND PERMISSIONS CHECK FOR PRIVATE PROJECTS */
        if (!$this->entriesUploadService->canUserUploadEntries()) {
            return Response::apiErrorCode(400, ['temp-file-upload' => ['ec5_78']]);
        }
        /* BUILD ENTRY STRUCTURE */
        $this->entryStructure->init(
            request()->input('data'),
            $this->requestedProject()->getId(),
            $this->requestedUser(),
            $this->requestedProjectRole()
        );

        try {
            $file = request()->file('file');
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            return Response::apiErrorCode(400, ['temp-file-upload' => ['ec5_116']]);
        }
        if (!$file) {
            return Response::apiErrorCode(400, ['temp-file-upload' => ['ec5_116']]);
        }
        $this->entryStructure->setFile($file);

        /* VALIDATE */
        if (!$ruleFileEntry->doesFileQuestionExist($this->requestedProject(), $this->entryStructure)) {
            return Response::apiErrorCode(400, $ruleFileEntry->errors());
        }
        // Validate web media file
        if (!$ruleFileEntry->isValidFile($this->entryStructure, true)) {
            return Response::apiErrorCode(400, $ruleFileEntry->errors());
        }
        /* STORE FILE */
        $projectRef = $this->requestedProject()->ref;
        // Get the entry data
        $fileEntry = $this->entryStructure->getEntry();
        $fileType = $fileEntry['type'];
        $fileName = $fileEntry['name'];
        $inputRef = $fileEntry['input_ref'];
        // Store the file into storage location, using the driver based on the file type
        $fileSaved = Storage::disk('temp')->put(
            $fileType . '/' . $projectRef . '/' . $fileName, // <-- renaming the file
            file_get_contents($file->getRealPath()),
            [
                'visibility' => 'public',
                'directory_visibility' => 'public'
            ]
        );
        // Check if put was successful
        if (!$fileSaved) {
            return Response::apiErrorCode(400, [$inputRef => ['ec5_83']]);
        }
        /* PASSED */
        return Response::apiSuccessCode('ec5_242');
    }
}
