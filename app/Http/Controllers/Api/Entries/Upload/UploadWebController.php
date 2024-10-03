<?php

declare(strict_types=1);

namespace ec5\Http\Controllers\Api\Entries\Upload;

use ec5\DTO\EntryStructureDTO;
use ec5\Libraries\Utilities\DateFormatConverter;
use ec5\Models\Counters\BranchEntryCounter;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use Exception;
use File;
use Log;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Response;
use Storage;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

class UploadWebController extends UploadControllerBase
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
     * @throws ContainerExceptionInterface
     * @throws Throwable
     * @throws NotFoundExceptionInterface
     */
    public function store()
    {
        //try to upload the entry
        if (!$this->entriesUploadService->upload()) {
            return Response::apiErrorCode(400, $this->entriesUploadService->errors);
        }
        //default response code for new entry
        $responseCode = 'ec5_237';
        $data = request()->get('data');
        $projectId = $this->requestedProject()->getId();

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
        $projectExtra = $this->requestedProject()->getProjectExtra();
        $formRef = $this->entryStructure->getFormRef();

        if (!$this->entryStructure->isBranch()) {
            $inputs = $projectExtra->getFormInputs($formRef);
        } else {
            $inputs = $projectExtra->getBranchInputs($formRef, $this->entryStructure->getOwnerInputRef());
        }

        $disk = Storage::disk('temp');
        $rootFolder = $disk->path('');

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
                    if (sizeof($this->errors) > 0) {
                        return Response::apiErrorCode(400, $this->errors);
                    }
                }
            } else {
                $this->moveFile($rootFolder, $input);
                if (sizeof($this->errors) > 0) {
                    return Response::apiErrorCode(400, $this->errors);
                }
            }
        }

        /**
         * imp: Remove any leftover branch entry
         *
         * This situation arises when branches are added via the web interface
         * and then they are skipped due to a jump, either during editing
         * or while navigating back and forth to adjust jump responses.
         *
         * If a branch reference is skipped in the owner entry, all branches associated with that reference and the owner entry UUID are deleted.
         *
         * Fixes this -> https://community.epicollect.net/t/online-data-and-download-data-differ/802/3
         */
        $this->removeLeftoverBranchEntries($formRef, $projectExtra);

        //Throttle for half a second so the server does not get smashed by uploads
        time_nanosleep(0, (int)(config('epicollect.setup.api.response_delay.upload')));


        /* PASSED */
        // Send http status code 200, ok!
        return Response::apiSuccessCode($responseCode);
    }

    /**
     *
     * Let's call the web upload controller @store method
     * We do this because the @storeBulk endpoint goes through
     * a middleware to check for bulk upload permissions
     */
    public function storeBulk()
    {
        $this->isBulkUpload = true;
        return $this->store();
    }

    /**
     * @param $rootFolder
     * @param $input
     * @return void
     */
    private function moveFile($rootFolder, $input): void
    {
        // If we don't have a media input type
        if (!in_array($input['type'], array_keys(config('epicollect.strings.media_input_types')))) {
            return;
        }

        $fileName = $this->entryStructure->getValidatedAnswer($input['ref'])['answer'];
        $filePath = $rootFolder . $input['type'] . '/' . $this->requestedProject()->ref . '/' . $fileName;

        // If the answer is empty
        // Or if we don't have a file for this input in the temp folder
        if (empty($fileName) || !File::exists($filePath)) {
            return;
        }

        try {
            $file = new UploadedFile(
                $filePath,
                $fileName,
                mime_content_type($filePath),
                filesize($filePath)
            );
        } catch (Throwable $e) {
            // File doesn't exist
            $this->errors['web upload'] = ['ec5_231'];
            return;
        }

        // Load everything into an entry structure model
        $entryStructure = new EntryStructureDTO();

        $entryData = config('epicollect.structures.entry_data');
        $entryData['id'] = $this->entryStructure->getEntryUuid();
        $entryData['type'] = config('epicollect.strings.entry_types.file_entry');
        $entryData[$entryData['type']] = [
            'type' => $input['type'],
            'name' => $fileName,
            'input_ref' => $input['ref']
        ];

        $entryStructure->createStructure($entryData);
        $entryStructure->setFile($file);

        // Move file
        // Note: the file has already been validated on initial upload to temp folder
        $this->ruleFileEntry->moveFile($this->requestedProject(), $entryStructure);
        if ($this->ruleFileEntry->hasErrors()) {
            $this->errors = $this->ruleFileEntry->errors();
            return;
        }

        // Delete file from temp folder
        File::delete($filePath);

    }

    private function removeLeftoverBranchEntries($formRef, $projectExtra)
    {
        //if this is a branch, bail out (cannot have branches within branches)
        if ($this->entryStructure->isBranch()) {
            return;
        }

        //if this is not an edit, bail out
        $existingEntry = $this->entryStructure->getExisting();
        if (!$existingEntry) {
            return;
        }

        //are there any branches? Otherwise, bail out
        $branches = $projectExtra->getBranches($formRef);
        if (sizeof($branches) === 0) {
            return;
        }

        //get jumped input refs, if any
        $jumpedInputRefs = [];
        $answers = $this->entryStructure->getValidatedAnswers();
        foreach ($answers as $ref => $answer) {
            if ($answer['was_jumped'] === true) {
                $jumpedInputRefs[] = $ref;
            }
        }

        //do we have some jumped questions? Otherwise, bail out
        if (sizeof($jumpedInputRefs) === 0) {
            return;
        }

        //filter refs by those which are branch refs
        $skippedBranchRefs = [];
        foreach ($branches as $branchRef => $value) {
            if (in_array($branchRef, $jumpedInputRefs)) {
                //this branch was skipped, remove any leftovers
                $skippedBranchRefs[] = $branchRef;
            }
        }

        //if any leftovers, delete using branch ref and entry owner uuid, otherwise bail out
        if (sizeof($skippedBranchRefs) === 0) {
            return;
        }

        //perform the deletion
        try {
            //delete leftover branch entries
            $leftoverBranchEntries = BranchEntry::where('owner_uuid', $this->entryStructure->getEntryUuid())
                ->whereIn('owner_input_ref', $skippedBranchRefs)
                ->get();

            $allBranchEntries = BranchEntry::where('owner_uuid', $this->entryStructure->getEntryUuid())
                ->get();


            $leftoverBranchEntries->each(function ($entry) {
                $entry->delete();
            });

            //update branch counts
            /**
             * imp: artificially set the owner uuid to itself in the entryStructure DTO,
             * imp: to re-use the branch update counter methods
             */
            $this->entryStructure->setOwnerEntryUuid($this->entryStructure->getEntryUuid());
            $entryCounter = new BranchEntryCounter();
            if (!$entryCounter->updateCounters($this->requestedProject(), $this->entryStructure)) {
                throw new Exception('Cannot update branch entries counters after leftover deletion');
            }
        } catch (\Throwable $e) {
            Log::error('Could not delete leftover branches', [
                'owner_uuid' => $this->entryStructure->getEntryUuid(),
                'owner_input_ref(s)' => $skippedBranchRefs,
                'exception' => $e->getMessage()
            ]);
        }
    }
}
