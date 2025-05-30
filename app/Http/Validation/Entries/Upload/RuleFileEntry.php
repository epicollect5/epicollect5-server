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
use ec5\Services\PhotoSaverService;
use File;
use Illuminate\Support\Facades\Storage;

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

    /**
     * @var RulePhotoApp
     */
    protected $rulePhotoApp;

    /**
     * @var RulePhotoWeb
     */
    protected $rulePhotoWeb;

    /**
     * @var RuleVideo
     */
    protected $ruleVideo;

    /**
     * @var RuleAudio
     */
    protected $ruleAudio;

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
    public function additionalChecks(ProjectDTO $project, EntryStructureDTO $entryStructure)
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
        if (!$this->fileInputExists($project, $entryStructure)) {
            // Get input_ref and entry
            $fileEntry = $entryStructure->getEntry();
            $inputRef = $fileEntry['input_ref'];

            //the error is $this->errors[$inputRef] = ['ec5_84'];
            unset($this->errors[$inputRef]);
            return;
        }

        //errors are logged inside the function
        if (!$this->fileEntryExists($project, $entryStructure)) {

            //here we got an orphan file, ignore it
            //and remove the error for that entry
            //we do this due to a bug on the app

            // Get uuid and entry
            $entryUuid = $entryStructure->getEntryUuid();

            //the error is $this->errors[$entryUuid] = ['ec5_46'];
            unset($this->errors[$entryUuid]);
            return;
        }

        $this->moveFile($project, $entryStructure);
    }

    /**
     * @param EntryStructureDTO $entryStructure
     * @param bool $isWebFile
     * @return bool
     */
    public function isValidFile(EntryStructureDTO $entryStructure, $isWebFile = false): bool
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
            } catch (\Throwable $e) {
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

    public function fileInputExists(ProjectDTO $project, EntryStructureDTO $entryStructure): bool
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

    /**
     * @param ProjectDTO $project
     * @param EntryStructureDTO $entryStructure
     */
    public function moveFile(ProjectDTO $project, EntryStructureDTO $entryStructure)
    {
        $projectRef = $project->ref;

        // Get the entry data
        $fileEntry = $entryStructure->getEntry();

        $fileType = $fileEntry['type'];
        $fileName = $fileEntry['name'];
        $inputRef = $fileEntry['input_ref'];


        // Process each file type
        switch ($fileType) {

            case config('epicollect.strings.inputs_type.photo'):

                // Entry original image
                list($width, $height) = getimagesize($entryStructure->getFile()->getRealPath());

                // Check if it's landscape
                if ($width > $height) {
                    // Set landscape dimensions
                    $dimensions = config('epicollect.media.entry_original_landscape');
                } else {
                    // Otherwise it's portrait (or square)
                    // Set portrait dimensions
                    $dimensions = config('epicollect.media.entry_original_portrait');
                }

                // If dimensions are already as desired, no need to resize
                // $dimensions[0] is always width, $dimensions[1] is always height
                if ($width == $dimensions[0] && $height == $dimensions[1]) {
                    // Reset the dimensions param to empty array to pass to saveImage() function
                    $dimensions = [];
                }

                // Attempt to save the original image (resized if necessary) keeping 100% quality
                $original = PhotoSaverService::saveImage(
                    $projectRef,
                    $entryStructure->getFile(),
                    $fileName,
                    'entry_original',
                    $dimensions,
                    100
                );

                // Check if any errors creating/saving original image
                if (!$original) {
                    $this->errors[$inputRef] = ['ec5_82'];
                    return;
                }

                // Entry thumb image

                // Create and save entry thumbnail image for photos, using 'entry_thumb' driver
                $thumb = PhotoSaverService::saveImage(
                    $projectRef,
                    $entryStructure->getFile(),
                    $fileName,
                    'entry_thumb',
                    config('epicollect.media.entry_thumb')
                );

                // Check if any errors creating/saving thumb
                if (!$thumb) {
                    $this->errors[$inputRef] = ['ec5_82'];
                    return;
                }

                break;

            default:

                // Get the driver specified in config - media.php
                $driver = $fileType;
                // Store the file into storage location, using driver based on the file type
                $fileSaved = Storage::disk($driver)->put(
                    $projectRef . '/' . $fileName,
                    file_get_contents($entryStructure->getFile()->getRealPath()),
                    [
                        'visibility' => 'public',
                        'directory_visibility' => 'public'
                    ]
                );

                // Check if put was successful
                if (!$fileSaved) {
                    $this->errors[$inputRef] = ['ec5_83'];
                    return;
                }
        }
    }

    //not used, here for reference
    public function removeOrphanFile(EntryStructureDTO $entryStructure): bool
    {
        $fileRealPath = $entryStructure->getFile()->getRealPath();
        File::delete($fileRealPath);
        return true;
    }

    //legacy, not used anymore
    public function moveOrphanFile(ProjectDTO $project, EntryStructureDTO $entryStructure)
    {
        $projectRef = $project->ref;

        // Get the entry data
        $fileEntry = $entryStructure->getEntry();
        $fileType = $fileEntry['type'];
        $fileName = $fileEntry['name'];
        $inputRef = $fileEntry['input_ref'];

        // Process each file type
        switch ($fileType) {

            case 'photo':

                // Entry original image
                list($width, $height) = getimagesize($entryStructure->getFile()->getRealPath());

                // Check if it's landscape
                if ($width > $height) {
                    // Set landscape dimensions
                    $dimensions = config('epicollect.media.entry_original_landscape');
                } else {
                    // Otherwise it's portrait (or square)
                    // Set portrait dimensions
                    $dimensions = config('epicollect.media.entry_original_portrait');
                }

                // If dimensions are already as desired, no need to resize
                // $dimensions[0] is always width, $dimensions[1] is always height
                if ($width == $dimensions[0] && $height == $dimensions[1]) {
                    // Reset the dimensions param to empty array to pass to saveImage() function
                    $dimensions = [];
                }

                // Attempt to save the original image (resized if necessary) keeping 100% quality
                $original = PhotoSaverService::saveImage(
                    $projectRef,
                    $entryStructure->getFile(),
                    $fileName,
                    'orphan_entry_original',
                    $dimensions,
                    100
                );

                // Check if any errors creating/saving original image
                if (!$original) {
                    $this->errors[$inputRef] = ['ec5_82'];
                    return;
                }

                // Entry thumb image

                // Create and save entry thumbnail image for photos, using 'entry_thumb' driver
                $thumb = PhotoSaverService::saveImage(
                    $projectRef,
                    $entryStructure->getFile(),
                    $fileName,
                    'orphan_entry_thumb',
                    config('epicollect.media.entry_thumb')
                );

                // Check if any errors creating/saving thumb
                if (!$thumb) {
                    $this->errors[$inputRef] = ['ec5_82'];
                    return;
                }

                break;

            default:

                // Get the driver specified in config - media.php
                $driver = 'orphan_' . $fileType;

                // Store the file into storage location, using driver based on the file type
                $fileSaved = Storage::disk($driver)->put(
                    $projectRef . '/' . $fileName,
                    file_get_contents($entryStructure->getFile()->getRealPath()),
                    [
                        'visibility' => 'public',
                        'directory_visibility' => 'public'
                    ]
                );

                // Check if put was successful
                if (!$fileSaved) {
                    $this->errors[$inputRef] = ['ec5_83'];
                    return;
                }
        }
    }
}
