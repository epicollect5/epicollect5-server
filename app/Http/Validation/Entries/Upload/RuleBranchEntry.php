<?php

namespace ec5\Http\Validation\Entries\Upload;

use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;

class RuleBranchEntry extends EntryValidationBase
{
    public function __construct(RuleAnswers $ruleAnswers)
    {
        parent::__construct($ruleAnswers);
    }

    /**
     * Function for additional checks
     * Checking that any relationships are valid
     *
     * @param ProjectDTO $project
     * @param EntryStructureDTO $branchEntryStructure
     *
     */
    public function additionalChecks(ProjectDTO $project, EntryStructureDTO $branchEntryStructure): void
    {
        $projectExtra = $project->getProjectExtra();

        $formRef = $branchEntryStructure->getFormRef();

        // Check branch input was supplied
        $branchOwnerInputRef = $branchEntryStructure->getOwnerInputRef();
        if (empty($branchOwnerInputRef)) {
            $this->errors[$branchOwnerInputRef] = ['ec5_17'];
            return;
        }

        // Check branch input exists
        if (!$projectExtra->branchExists($formRef, $branchOwnerInputRef)) {
            $this->errors[$branchOwnerInputRef] = ['ec5_17'];
            return;
        }

        // Check the 'branch entry owner' (the hierarchy entry) exists
        $branchOwnerUuid = $branchEntryStructure->getOwnerUuid();//this is the one from the request
        $owner = Entry::where('uuid', '=', $branchOwnerUuid)->first();
        // If we have no owner entry
        if (!$owner) {
            $this->errors[$branchOwnerInputRef] = ['ec5_17'];
            return;
        }
        $branchEntryStructure->setOwnerEntryID($owner->id);

        /* DETERMINE WHETHER ADD OR EDIT */
        // Check if this entry can be edited
        if (!$this->checkCanEdit($branchEntryStructure, $project->getId())) {
            $this->errors['upload'] = ['ec5_54'];
            return;
        }

        //check if request owner_uuid matches the one in the database (for bulk upload edits)
        if (!$this->checkMatchingOwnerUuid($branchEntryStructure)) {
            $this->errors['upload'] = ['ec5_358'];
            return;
        }


        /* ANSWERS VALIDATION */
        // Get branch inputs
        $inputs = $projectExtra->getBranchInputs($branchEntryStructure->getFormRef(), $branchOwnerInputRef);

        if (count($inputs) == 0) {
            // Form inputs don't exist
            $this->errors['upload'] = ['ec5_15'];
            return;
        }

        // Validate the answers
        $this->validateAnswers($project, $branchEntryStructure, $inputs);

    }

    /**
     * @param EntryStructureDTO $branchEntryStructure
     * @return bool
     *
     * When bulk uploading branches for edits, users are providing the uuid
     * so we need to edit only the branches which belongs to the current owner entry.
     *
     * If they upload other branch entries with a different owner uuid but the same project,
     * they would edit those entries without even knowing
     */
    public function checkMatchingOwnerUuid(EntryStructureDTO $branchEntryStructure): bool
    {
        // Check the branch entry owner exists
        $branchOwnerUuid = $branchEntryStructure->getOwnerUuid();//this one if from the request
        $branchUuid = $branchEntryStructure->getEntryUuid();

        //check in the branch entries table if this owner_uuid is the right one for the branch uuid
        $owner = BranchEntry::where('uuid', '=', $branchUuid)->first();
        if ($owner === null) {
            //no branch found, this is a new branch to add
            return true;
        }

        //branch found so it is an edit, do they match?
        return $branchOwnerUuid === $owner->owner_uuid;
    }
}
