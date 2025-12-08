<?php

namespace ec5\Http\Validation\Entries\Upload;

use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;
use ec5\Libraries\Utilities\Strings;
use ec5\Models\Entries\Entry;
use Log;

class RuleEntry extends EntryValidationBase
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
     * @param EntryStructureDTO $entryStructure
     */
    public function additionalChecks(ProjectDTO $project, EntryStructureDTO $entryStructure): void
    {
        $projectExtra = $project->getProjectExtra();
        $projectDefinition = $project->getProjectDefinition();
        $formRef = $entryStructure->getFormRef();

        // Check if this entry should have a parent entry uuid
        $parentFormRef = $projectDefinition->getParentFormRef($formRef);

        //check entry uuid is in correct format -> todo
        if (!Strings::isValidUuid($entryStructure->getEntryUuid())) {
            Log::error('uuid is invalid: ', ['project' => $project, 'uuid' => $entryStructure->getEntryUuid()]);
            $this->errors[$formRef] = ['ec5_334'];
            return;
        }

        if ($parentFormRef !== null) {
            $entryParentFormRef = $entryStructure->getParentFormRef();
            // Check the parent form is a direct ancestor
            if ($parentFormRef !== $entryParentFormRef) {
                $this->errors[$entryParentFormRef] = ['ec5_18'];
                return;
            }

            // Check the parent form actually exists
            if (!$projectExtra->formExists($entryParentFormRef)) {
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
            $entryModel = new Entry();
            $parent = $entryModel->getParentEntry($parentEntryUuid, $entryParentFormRef);

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
     * @param EntryStructureDTO $entryStructure
     * @return bool
     *
     * When bulk uploading child entries for editing, check the parent uuid for matches.
     * This could happen when the user is viewing the child entries of a parent entry but try to
     * upload the full child entries dataset, without this check he would end up
     * editing entries he did not want to.
     */
    public function checkMatchingParentUuid(EntryStructureDTO $entryStructure): bool
    {
        // Check parent entry uuid
        $parentEntryUuid = $entryStructure->getParentUuid();//this one if from the request
        $entryUuid = $entryStructure->getEntryUuid();//this is from the request as well

        //check in the entries table if this parent_uuid is the right one for the child uuid
        $entry = Entry::where('uuid', '=', $entryUuid)->first();

        //if no entry is found, this is a new entry not an edit
        if ($entry === null) {
            return true;
        }

        //entry found, so do they match?
        return $parentEntryUuid === $entry->parent_uuid;
    }
}
