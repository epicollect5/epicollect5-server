<?php

namespace ec5\Http\Validation\Entries\Upload;

use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\Entries\Upload\FileRules\RuleAudio;
use ec5\Http\Validation\Entries\Upload\FileRules\RulePhotoApp;
use ec5\Http\Validation\Entries\Upload\FileRules\RulePhotoWeb;
use ec5\Http\Validation\Entries\Upload\FileRules\RuleVideo;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use Throwable;

class RuleFileEntry extends EntryValidationBase
{
    protected array $rules = [
        'type' => 'required|in:audio,video,photo',
        'name' => 'required',
        'input_ref' => 'required'
    ];

    protected array $messages = [
        'required' => 'ec5_20',
        'in' => 'ec5_47'
    ];

    protected RulePhotoApp $rulePhotoApp;
    protected RulePhotoWeb $rulePhotoWeb;
    protected RuleVideo $ruleVideo;
    protected RuleAudio $ruleAudio;

    public function __construct(
        RulePhotoApp $rulePhotoApp,
        RulePhotoWeb $rulePhotoWeb,
        RuleVideo    $ruleVideo,
        RuleAudio    $ruleAudio,
        RuleAnswers  $ruleAnswers
    ) {
        $this->rulePhotoApp = $rulePhotoApp;
        $this->rulePhotoWeb = $rulePhotoWeb;
        $this->ruleVideo = $ruleVideo;
        $this->ruleAudio = $ruleAudio;

        // Call to parent constructor, default to $entrySearchRepository
        parent::__construct($ruleAnswers);
    }

    /**
     * Function for additional checks
     * Will store the uploaded file here
     *
     * @param ProjectDTO $project
     * @param EntryStructureDTO $entryStructure
     */
    public function additionalChecks(ProjectDTO $project, EntryStructureDTO $entryStructure): void
    {
        if (!$this->isValidFile($entryStructure)) {
            return;
        }

        /**
         * If the file does not have a question it belongs to, it means the user is uploading
         * some media files for a question which got deleted. Updating the project on the mobile app
         * does not consider these files (at least on version 2.0.9 and below)
         * therefore, let's go on with the upload but ignore the file and clear the error
         *
         */
        if (!$this->doesFileQuestionExist($project, $entryStructure)) {
            // Get input_ref and entry
            $fileEntry = $entryStructure->getEntry();
            $inputRef = $fileEntry['input_ref'];

            //the error is $this->errors[$inputRef] = ['ec5_84'];
            unset($this->errors[$inputRef]);
            //flag this file as orphan
            $entryStructure->flagFileAsOrphan();
            return;
        }

        //errors are logged inside the function
        if (!$this->fileEntryExists($project, $entryStructure)) {

            //here we got an orphan file, ignore it
            //and remove the error for that entry
            //we do this due to a bug on the app

            // Get uuid and entry
            $entryUuid = $entryStructure->getEntryUuid();

            //flag this file as orphan
            $entryStructure->flagFileAsOrphan();

            //the error is $this->errors[$entryUuid] = ['ec5_46'];
            unset($this->errors[$entryUuid]);
        }
    }

    /**
     * @param EntryStructureDTO $entryStructure
     * @param bool $isWebFile
     * @return bool
     */
    public function isValidFile(EntryStructureDTO $entryStructure, bool $isWebFile = false): bool
    {
        // Get the entry data
        $fileEntry = $entryStructure->getEntry();
        $fileType = $fileEntry['type'];
        $entryUuid = $entryStructure->getEntryUuid();

        // Use validator related to the file type
        switch ($fileType) {
            case 'video':
                $validator = $this->ruleVideo;
                break;
            case 'audio':
                $validator = $this->ruleAudio;
                break;
            default:
                // If the file came from a web upload, use different set of rules
                if ($isWebFile) {
                    $validator = $this->rulePhotoWeb;
                } else {
                    // Otherwise app rules
                    $validator = $this->rulePhotoApp;
                }
        }

        // Validate the file
        $data['file'] = $entryStructure->getFile();

        // Check exists i.e. not null
        if (empty($data['file'])) {
            //this happens if there is a timeout error
            $this->errors[$entryUuid] = ['ec5_69'];
            return false;
        }

        // Photos also need their dimensions checked
        if ($fileType == 'photo') {
            //this would fail when it is not a valid file, it is a way to check for trojans
            try {
                list($width, $height) = getimagesize($entryStructure->getFile()->getRealPath());
                // Add to input array to be validated
                $data['width'] = $width;
                $data['height'] = $height;
            } catch (Throwable) {
                $this->errors[$entryUuid] = ['ec5_83'];
                return false;
            }
        }

        $validator->validate($data);

        if ($validator->hasErrors()) {
            $this->errors = $validator->errors();
            return false;
        }

        return true;
    }

    public function fileEntryExists(ProjectDTO $project, EntryStructureDTO $entryStructure): bool
    {
        $projectExtra = $project->getProjectExtra();
        // Get the entry data
        $fileEntry = $entryStructure->getEntry();
        $inputRef = $fileEntry['input_ref'];
        $formRef = $entryStructure->getFormRef();

        // Get uuid and entry
        $entryUuid = $entryStructure->getEntryUuid();

        // Check if this file is part of a branch entry
        if ($projectExtra->isBranchInput($formRef, $inputRef)) {
            $entry = BranchEntry::where('uuid', '=', $entryUuid)->first();
        } else {
            $entry = Entry::where('uuid', '=', $entryUuid)->first();
        }
        // Check this entry exists
        if (!$entry) {
            $this->errors[$entryUuid] = ['ec5_46'];
            return false;
        }
        return true;
    }

    /**
     * Checks if the question (input_ref) for a file exists in the project's definition.
     *
     * Returns false and sets an error if the input reference is missing or invalid.
     *
     * @return bool True if the input reference exists; false otherwise.
     */
    public function doesFileQuestionExist(ProjectDTO $project, EntryStructureDTO $entryStructure): bool
    {
        $projectExtra = $project->getProjectExtra();
        // Get the entry data
        $fileEntry = $entryStructure->getEntry();
        $inputRef = $fileEntry['input_ref'];

        // Check the input exists
        $input = $projectExtra->getInputData($inputRef);

        if (count($input) == 0) {
            $this->errors[$inputRef] = ['ec5_84'];
            return false;
        }

        return true;
    }
}
