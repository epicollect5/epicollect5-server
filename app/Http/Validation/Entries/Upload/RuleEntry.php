<?php

namespace ec5\Http\Validation\Entries\Upload;

use ec5\Models\Projects\Project;
use ec5\Repositories\QueryBuilder\Entry\Upload\Search\EntryRepository as EntrySearchRepository;

use ec5\Http\Validation\Entries\Upload\RuleAnswers as AnswerValidator;

use ec5\Models\Entries\EntryStructure;
use Log;
use ec5\Libraries\Utilities\Strings;

class RuleEntry extends EntryValidationBase
{

    /**
     * RuleEntry constructor.
     * @param EntrySearchRepository $entrySearchRepository
     * @param RuleAnswers $answerValidator
     */
    public function __construct(EntrySearchRepository $entrySearchRepository, AnswerValidator $answerValidator)
    {
        parent::__construct($entrySearchRepository, $answerValidator);
    }

    /**
     * Function for additional checks
     * Checking that any relationships are valid
     *
     * @param Project $project
     * @param EntryStructure $entryStructure
     */
    public function additionalChecks(Project $project, EntryStructure $entryStructure)
    {

        $projectExtra = $project->getProjectExtra();

        $formRef = $entryStructure->getFormRef();

        // Check if this entry should have a parent entry uuid
        $hasParent = $projectExtra->formHasParent($formRef);

        //check entry uuid is in correct format -> todo
        if (!Strings::isValidUuid($entryStructure->getEntryUuid())) {
            Log::error('uuid is invalid: ', ['project' => $project, 'uuid' => $entryStructure->getEntryUuid()]);
            $this->errors[$formRef] = ['ec5_334'];
            return;
        }

        if ($hasParent) {

            $parentFormRef = $entryStructure->getParentFormRef();

            // Check parent form is a direct ancestor
            if ($hasParent != $parentFormRef) {
                $this->errors[$parentFormRef] = ['ec5_18'];
                return;
            }

            $parentForm = $projectExtra->getFormDetails($parentFormRef);

            // Check parent form exists
            if (count($parentForm) == 0) {
                $this->errors[$formRef] = ['ec5_15'];
                return;
            }

            $parentEntryUuid = $entryStructure->getParentUuid();

            // If we don't have a parent entry
            if (empty($parentEntryUuid)) {
                $this->errors[$formRef] = ['ec5_19'];
                return;
            }

            // Check parent entry exists for this parent uuid and parent form ref
            $parent = $this->searchRepository->getParentEntry($parentEntryUuid, $parentFormRef);

            if (!$parent) {
                $this->errors[$formRef] = ['ec5_19'];
                return;
            }
        }

        //check if parent_uuid in the request matches the one in the database (for bulk upload edits)
        if (!$this->checkMatchingParentUuid($entryStructure)) {
            $this->errors['upload'] = ['ec5_359'];
            return;
        }

        /* DETERMINE WHETHER ADD OR EDIT */
        // Check if this entry can be edited
        if (!$this->checkCanEdit($entryStructure, $project->getId())) {
            $this->errors['upload'] = ['ec5_54'];
            return;
        }

        /* ANSWERS VALIDATION */

        // Get form inputs
        $inputs = $projectExtra->getFormInputs($entryStructure->getFormRef());
        if (count($inputs) == 0) {
            // Form inputs don't exist
            $this->errors['upload'] = ['ec5_15'];
            return;
        }

        // Validate the answers
        $this->validateAnswers($project, $entryStructure, $inputs);
    }

    /**
     * @param EntryStructure $entryStructure
     * @return bool
     *
     * When bulk uploading child entries for editing, check the parent uuid for matches.
     * This could happen when the user is viewing the child entries of a parent entry but try to
     * upload the full child entries dataset, without this check he would end up
     * editing entries he did not want to.
     */
    public function checkMatchingParentUuid(EntryStructure $entryStructure)
    {
        // Check parent entry uuid
        $parentEntryUuid = $entryStructure->getParentUuid();//this one if from the request
        $entryUuid = $entryStructure->getEntryUuid();//this is from the request as well

        //check in the entries table if this parent_uuid is the right one for the child uuid
        $entry = $this->searchRepository->where('uuid', '=', $entryUuid);

        //if no entry is found, this is a new entry not an edit
        if ($entry === null) {
            return true;
        }

        //entry found, so do they match?
        return $parentEntryUuid === $entry->parent_uuid;
    }
}
