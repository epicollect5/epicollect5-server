<?php

namespace ec5\Http\Validation\Entries\Upload;

use ec5\Models\Projects\Project;
use ec5\Repositories\QueryBuilder\Entry\Upload\Search\BranchEntryRepository as BranchEntrySearchRepository;
use ec5\Repositories\QueryBuilder\Entry\Upload\Search\EntryRepository as EntrySearchRepository;

use ec5\Http\Validation\Entries\Upload\FileRules\RulePhotoApp as PhotoAppValidator;
use ec5\Http\Validation\Entries\Upload\FileRules\RulePhotoWeb as PhotoWebValidator;
use ec5\Http\Validation\Entries\Upload\FileRules\RuleVideo as VideoValidator;
use ec5\Http\Validation\Entries\Upload\FileRules\RuleAudio as AudioValidator;
use ec5\Http\Validation\Entries\Upload\RuleAnswers as AnswerValidator;

use ec5\Models\Entries\EntryStructure;
use ec5\Models\Images\UploadImage;

use Illuminate\Support\Facades\Storage;

use Config;
use Log;
use Exception;
use File;

class RuleFileEntry extends EntryValidationBase
{
    protected $rules = [
        'type' => 'required|in:audio,video,photo',
        'name' => 'required',
        'input_ref' => 'required'
    ];

    protected $messages = [
        'required' => 'ec5_20',
        'in' => 'ec5_47'
    ];

    /**
     * @var EntrySearchRepository
     */
    protected $entrySearchRepository;

    /**
     * @var BranchEntrySearchRepository
     */
    protected $branchEntrySearchRepository;

    /**
     * @var PhotoAppValidator
     */
    protected $photoAppValidator;

    /**
     * @var PhotoWebValidator
     */
    protected $photoWebValidator;

    /**
     * @var VideoValidator
     */
    protected $videoValidator;

    /**
     * @var AudioValidator
     */
    protected $audioValidator;

    /**
     * RuleFileEntry constructor.
     * @param EntrySearchRepository $entrySearchRepository
     * @param BranchEntrySearchRepository $branchEntrySearchRepository
     * @param PhotoAppValidator $photoAppValidator
     * @param PhotoWebValidator $photoWebValidator
     * @param VideoValidator $videoValidator
     * @param AudioValidator $audioValidator
     * @param RuleAnswers $answerValidator
     */
    function __construct(
        EntrySearchRepository       $entrySearchRepository,
        BranchEntrySearchRepository $branchEntrySearchRepository,
        PhotoAppValidator           $photoAppValidator,
        PhotoWebValidator           $photoWebValidator,
        VideoValidator              $videoValidator,
        AudioValidator              $audioValidator,
        AnswerValidator             $answerValidator
    )
    {
        $this->entrySearchRepository = $entrySearchRepository;
        $this->branchEntrySearchRepository = $branchEntrySearchRepository;

        $this->photoAppValidator = $photoAppValidator;
        $this->photoWebValidator = $photoWebValidator;
        $this->videoValidator = $videoValidator;
        $this->audioValidator = $audioValidator;

        // Call to parent constructor, default to $entrySearchRepository
        parent::__construct($entrySearchRepository, $answerValidator);
    }

    /**
     * Function for additional checks
     * Will store the uploaded file here
     *
     * @param Project $project
     * @param EntryStructure $entryStructure
     */
    public function additionalChecks(Project $project, EntryStructure $entryStructure)
    {
        if (!$this->isValidFile($entryStructure)) {
            return;
        }

        /**
         * If the file does not have a question it belongs to, it means the user is uploading
         * some media files for a question which got deleted. Updating the project on the mobile app
         * does not consider these files (at least on version 2.0.9 and below)
         * therefore let's go on with the upload but ignore the file and clear the error
         *
         * we can purge the orphan folder every now and then,
         * going forward no files will be saved there anyway
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

            //here we got an orphan file, move it to orphan folder
            //and remove the error for that entry
            //we do this due to a bug on the app....

            // Get uuid and entry
            $entryUuid = $entryStructure->getEntryUuid();

            //the error is $this->errors[$entryUuid] = ['ec5_46'];
            unset($this->errors[$entryUuid]);
            return;
        }

        $this->moveFile($project, $entryStructure);

        if ($this->hasErrors()) {
        }
    }

    /**
     * @param EntryStructure $entryStructure
     * @param bool $isWebFile
     * @return bool
     */
    public function isValidFile(EntryStructure $entryStructure, $isWebFile = false)
    {
        // Get the entry data
        $fileEntry = $entryStructure->getEntry();
        $fileType = $fileEntry['type'];
        $entryUuid = $entryStructure->getEntryUuid();

        // Use validator related to file type
        switch ($fileType) {
            case 'video':
                $validator = $this->videoValidator;
                break;
            case 'audio':
                $validator = $this->audioValidator;
                break;
            default:
                // If the file came from a web upload, use different set of rules
                if ($isWebFile) {
                    $validator = $this->photoWebValidator;
                } else {
                    // Otherwise app rules
                    $validator = $this->photoAppValidator;
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
            } catch (Exception $e) {
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

    /**
     * @param Project $project
     * @param EntryStructure $entryStructure
     * @return bool
     */
    public function fileEntryExists(Project $project, EntryStructure $entryStructure)
    {
        $projectExtra = $project->getProjectExtra();
        // Get the entry data
        $fileEntry = $entryStructure->getEntry();
        $inputRef = $fileEntry['input_ref'];
        $formRef = $entryStructure->getFormRef();

        // todo can we check is branch another way? use relationships property
        // Check if this file is part of a branch entry
        if ($projectExtra->isBranchInput($formRef, $inputRef)) {
            $this->searchRepository = $this->branchEntrySearchRepository;
        }

        // Get uuid and entry
        $entryUuid = $entryStructure->getEntryUuid();
        $entry = $this->searchRepository->where('uuid', '=', $entryUuid);

        // Check this entry exists
        if (!$entry) {
            $this->errors[$entryUuid] = ['ec5_46'];

            //            //todo we do this because it is failing for many
            //            Log::error('fileEntryExists() warning: ' . $project->name, [
            //                'entryUuid' => $entryUuid,
            //                'entry' => $entry,
            //                'fileEntry' => $fileEntry,
            //                'formRef' => $formRef,
            //                'inputRef' => $inputRef,
            //                'entryData' => $entryStructure->getData()
            //            ]);
            return false;
        }
        return true;
    }

    /**
     * @param Project $project
     * @param EntryStructure $entryStructure
     * @return bool
     */
    public function fileInputExists(Project $project, EntryStructure $entryStructure)
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
     * @param Project $project
     * @param EntryStructure $entryStructure
     */
    public function moveFile(Project $project, EntryStructure $entryStructure)
    {
        $projectRef = $project->ref;

        // Get the entry data
        $fileEntry = $entryStructure->getEntry();

        $fileType = $fileEntry['type'];
        $fileName = $fileEntry['name'];
        $inputRef = $fileEntry['input_ref'];

        list($width, $height) = getimagesize($entryStructure->getFile()->getRealPath());

        // Process each file type
        switch ($fileType) {

            case 'photo':

                // Entry original image

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
                $original = UploadImage::saveImage(
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
                $thumb = UploadImage::saveImage(
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
                    file_get_contents($entryStructure->getFile()->getRealPath())
                );

                // Check if put was successful
                if (!$fileSaved) {
                    $this->errors[$inputRef] = ['ec5_83'];
                    return;
                }
        }
    }

    //not used, here for reference
    public function removeOrphanFile(EntryStructure $entryStructure)
    {
        $fileRealPath = $entryStructure->getFile()->getRealPath();
        File::delete($fileRealPath);
        return true;
    }

    //legacy, not used anymore
    public function moveOrphanFile(Project $project, EntryStructure $entryStructure)
    {
        $projectRef = $project->ref;

        // Get the entry data
        $fileEntry = $entryStructure->getEntry();
        $fileType = $fileEntry['type'];
        $fileName = $fileEntry['name'];
        $inputRef = $fileEntry['input_ref'];

        list($width, $height) = getimagesize($entryStructure->getFile()->getRealPath());

        // Process each file type
        switch ($fileType) {

            case 'photo':

                // Entry original image

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
                $original = UploadImage::saveImage(
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
                $thumb = UploadImage::saveImage(
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
                    file_get_contents($entryStructure->getFile()->getRealPath())
                );

                // Check if put was successful
                if (!$fileSaved) {
                    $this->errors[$inputRef] = ['ec5_83'];
                    return;
                }
        }
    }
}
