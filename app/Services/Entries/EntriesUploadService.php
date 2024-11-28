<?php

namespace ec5\Services\Entries;

use ec5\DTO\EntryStructureDTO;
use ec5\Http\Validation\Entries\Upload\RuleUpload;
use ec5\Models\Counters\BranchEntryCounter;
use ec5\Models\Counters\EntryCounter;
use ec5\Traits\Requests\RequestAttributes;
use Log;
use Throwable;

class EntriesUploadService
{
    use RequestAttributes;

    public array $errors;
    public EntryStructureDTO $entryStructure;
    private bool $isBulkUpload;
    private RuleUpload $ruleUpload;

    public function __construct(EntryStructureDTO $entryStructure, RuleUpload $ruleUpload, $isBulkUpload = false)
    {
        $this->entryStructure = $entryStructure;
        $this->isBulkUpload = $isBulkUpload;
        $this->ruleUpload = $ruleUpload;
    }

    /**
     * @throws Throwable
     */
    public function upload(): bool
    {
        try {
            $payload = request()->get('data');
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            $this->errors = ['upload-controller' => ['ec5_53']];
            return false;
        }
        /* UPLOAD VALIDATION */

        // 1 - Validate the api upload request
        $this->ruleUpload->validate($payload);
        if ($this->ruleUpload->hasErrors()) {
            $this->errors = $this->ruleUpload->errors();
            return false;
        }

        // 2 - Check project status
        if (!$this->isProjectActive()) {
            if ($this->requestedProject()->isLocked()) {
                $this->errors = ['upload-controller' => ['ec5_202']];
            }
            if ($this->requestedProject()->isTrashed()) {
                $this->errors = ['upload-controller' => ['ec5_11']];
            }
            return false;
        }

        /* 3 - USER AUTHENTICATION AND PERMISSIONS CHECK FOR PRIVATE PROJECTS */
        if (!$this->canUserUploadEntries()) {
            $this->errors = ['upload-controller' => ['ec5_78']];
            return false;
        }

        /* 4 - BUILD ENTRY STRUCTURE */
        $this->entryStructure->init(
            $payload,
            $this->requestedProject()->getId(),
            $this->requestedUser(),
            $this->requestedProjectRole()
        );

        // Check the project version this entry was created with
        if (!$this->isProjectVersionValid($this->entryStructure)) {
            $this->errors = ['upload-controller' => ['ec5_201']];
            return false;
        }

        /* ENTRY/ANSWERS VALIDATION */
        $this->ruleUpload->additionalChecks($payload, $this->requestedProject(), $this->entryStructure);
        if ($this->ruleUpload->hasErrors()) {
            $this->errors = $this->ruleUpload->errors();
            return false;
        }


        /* CHECK LIMITS NOT REACHED */
        //imp: this is calculated live, it does not use the project stats table
        if ($this->isEntriesLimitReached($this->entryStructure)) {
            $this->errors = ['upload-controller' => ['ec5_250']];
            return false;
        }

        /* INSERT ENTRY */
        // If we have answers to insert from our upload
        if ($this->entryStructure->hasAnswers()) {
            $createEntryService = new CreateEntryService();
            // If we received no errors, continue to insert answers and entry
            if (!$createEntryService->create(
                $this->requestedProject(),
                $this->entryStructure,
                $this->isBulkUpload
            )
            ) {
                Log::error(__METHOD__ . ' failed.', [
                    'error' => $this->errors,
                    'data' => $payload
                ]);
                $this->errors = $createEntryService->errors;
                return false;
            }
        }
        return true;
    }


    /**
     * @param EntryStructureDTO $entryStructure
     * @return bool
     */
    public function isEntriesLimitReached(EntryStructureDTO $entryStructure): bool
    {
        // If the entry is an edit, then it's ok to allow this entry in
        if ($entryStructure->isEdit()) {
            return false;
        }
        //new entry? let's see
        $projectDefinition = $this->requestedProject()->getProjectDefinition();
        // Branch or Form?
        if ($entryStructure->isBranch()) {
            $ref = $entryStructure->getOwnerInputRef();
        } else {
            $ref = $entryStructure->getFormRef();
        }

        //When no entries limit set, bail out
        $entriesLimit = $projectDefinition->getEntriesLimit($ref);
        if ($entriesLimit === null) {
            return false;
        }

        // Check the entries limit has not been reached for the form or branch
        if ($entryStructure->isBranch()) {
            $entryCounter = new BranchEntryCounter();
            $currentEntriesCount = $entryCounter->getBranchEntryCounts(
                $this->requestedProject()->getId(),
                $entryStructure->getFormRef(),
                $entryStructure->getOwnerInputRef(),
                $entryStructure->getOwnerUuid()
            );
        } else {
            $entryCounter = new EntryCounter();
            $currentEntriesCount = $entryCounter->getFormEntryCounts(
                $this->requestedProject()->getId(),
                $entryStructure->getFormRef(),
                $entryStructure->getParentUuid()
            );
        }
        // If we haven't reached the entries limit (Add 1 to include this entry), all good
        if (($currentEntriesCount + 1) <= $entriesLimit) {
            return false;
        }
        // Entries limit reached, throw error
        return true;
    }

    public function isProjectVersionValid(EntryStructureDTO $entryStructure): bool
    {
        // Log::debug(__METHOD__ . ' failed.', ['project version' => $this->requestedProject()->getProjectStats()->structure_last_updated]);
        //Log::debug(__METHOD__ . ' failed.', ['entry version' => $entryStructure->getProjectVersion()]);

        if ($this->requestedProject()->getProjectStats()->structure_last_updated !== $entryStructure->getProjectVersion()) {
            return false;
        }
        return true;
    }

    public function isProjectActive(): bool
    {
        if ($this->requestedProject()->status !== config('epicollect.strings.project_status.active')) {
            return false;
        }
        return true;
    }


    public function canUserUploadEntries(): bool
    {
        // Check user is permitted to upload if the project is private
        if ($this->requestedProject()->isPrivate() && !$this->requestedProjectRole()->canUpload()) {
            //Is someone posting via the POST API?
            // We check the route name, if it gets here it means it went through the auth,
            //so we add the entry without assigning any user to it.
            //This type of entry will be editable only CREATOR/MANAGER/CURATOR
            if (request()->route()->getName() === 'private-import') {
                return true;
            }
            $this->errors = ['upload-controller' => ['ec5_71']];
            return false;
        }
        return true;
    }
}
