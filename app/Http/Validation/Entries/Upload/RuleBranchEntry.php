<?php

namespace ec5\Http\Validation\Entries\Upload;

use ec5\Libraries\EC5Logger\EC5Logger;
use ec5\Models\Projects\Project;
use ec5\Repositories\QueryBuilder\Entry\Upload\Search\BranchEntryRepository as BranchEntrySearchRepository;
use ec5\Repositories\QueryBuilder\Entry\Upload\Search\EntryRepository as EntrySearchRepository;

use ec5\Http\Validation\Entries\Upload\RuleAnswers as AnswerValidator;

use ec5\Models\Entries\EntryStructure;

class RuleBranchEntry extends EntryValidationBase
{
    protected $entrySearchRepository;

    /**
     * RuleBranchEntry constructor.
     * @param EntrySearchRepository $entrySearchRepository
     * @param BranchEntrySearchRepository $branchEntrySearchRepository
     * @param RuleAnswers $answerValidator
     */
    public function __construct(EntrySearchRepository $entrySearchRepository, BranchEntrySearchRepository $branchEntrySearchRepository, AnswerValidator $answerValidator)
    {
        $this->entrySearchRepository = $entrySearchRepository;

        parent::__construct($branchEntrySearchRepository, $answerValidator);
    }

    /**
     * Function for additional checks
     * Checking that any relationships are valid
     *
     * @param Project $project
     * @param EntryStructure $branchEntryStructure
     */
    public function additionalChecks(Project $project, EntryStructure $branchEntryStructure)
    {

        $projectExtra = $project->getProjectExtra();

        $branchEntryStructure->setAsBranch();

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

        // Check branch entry owner exists
        $branchOwnerUuid = $branchEntryStructure->getOwnerUuid();//this is the one from the request
        $owner = $this->entrySearchRepository->where('uuid', '=', $branchOwnerUuid);

        // If we have no owner entry
        if (!$owner) {
            $this->errors[$branchOwnerInputRef] = ['ec5_17'];
            return;
        }
        $branchEntryStructure->addBranchOwnerEntryToStructure($owner);

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
            EC5Logger::error('Branch upload failed - inputs dont exist', $project, $inputs);
            $this->errors['upload'] = ['ec5_15'];
            return;
        }

        // Validate the answers
        $this->validateAnswers($project, $branchEntryStructure, $inputs);

    }

    /**
     * @param EntryStructure $branchEntryStructure
     * @return bool
     *
     * When bulk uploading branches for edits, users are providing the uuid
     * so we need to edit only the branches which belongs to the current owner entry.
     *
     * If they upload other branch entries with a different owner uuid but the same project,
     * they would edit those entries without even knowing
     */
    public function checkMatchingOwnerUuid(EntryStructure $branchEntryStructure)
    {
        // Check branch entry owner exists
        $branchOwnerUuid = $branchEntryStructure->getOwnerUuid();//this one if from the request
        $branchUuid = $branchEntryStructure->getEntryUuid();

        //check in the branch entries table if this owner_uuid is the right one for the branch uuid
        //searchRepository -> this must be the branch entry search?
        $owner = $this->searchRepository->where('uuid', '=', $branchUuid);

        if($owner === null) {
            //no branch found, this is a new branch to add
            return true;
        }

        //branch found so it is an edit, do they match?
        return $branchOwnerUuid === $owner->owner_uuid;
    }
}
