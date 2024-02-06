<?php

namespace ec5\Http\Controllers\Api\Entries\Upload;

use ec5\Http\Validation\Entries\Upload\RuleFileEntry;
use Response;
use Storage;

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
            request()->get('data'),
            $this->requestedProject()->getId(),
            $this->requestedProjectRole()
        );

        $file = request()->file('file');
        if (!$file) {
            return Response::apiErrorCode(400, ['temp-file-upload' => ['ec5_116']]);
        }
        $this->entryStructure->setFile($file);

        /* VALIDATE */
        if (!$ruleFileEntry->fileInputExists($this->requestedProject(), $this->entryStructure)) {
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
            $fileType . '/' . $projectRef . '/' . $fileName,
            file_get_contents($file->getRealPath())
        );
        // Check if put was successful
        if (!$fileSaved) {
            return Response::apiErrorCode(400, [$inputRef => ['ec5_83']]);
        }
        /* PASSED */
        return Response::apiSuccessCode('ec5_242');
    }
}
