<?php

namespace ec5\Http\Validation\Entries\Upload;

use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\ValidationBase;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;

abstract class EntryValidationBase extends ValidationBase
{
    /*
     * This class contains common functions for entry/branch_entry uploads
    */

    protected RuleAnswers $ruleAnswers;

    public function __construct(RuleAnswers $ruleAnswers)
    {
        $this->ruleAnswers = $ruleAnswers;
    }

    /**
     * Function for additional checks
     *
     * @param ProjectDTO $project
     * @param EntryStructureDTO $entryStructure
     */
    abstract public function additionalChecks(ProjectDTO $project, EntryStructureDTO $entryStructure);

    /**
     * @param ProjectDTO $project
     * @param EntryStructureDTO $entryStructure
     * @param $inputs - maybe form or branch entry inputs
     */
    protected function validateAnswers(ProjectDTO $project, EntryStructureDTO $entryStructure, $inputs): void
    {
        $projectExtra = $project->getProjectExtra();
        $entryAnswers = $entryStructure->getAnswers();

        // Loop round the form inputs and validate the relevant answer
        foreach ($inputs as $inputRef) {

            $inputType = $projectExtra->getInputDetail($inputRef, 'type');

            // If the input type requires an answer (all but group and readme)
            if (!in_array($inputType, array_keys(config('epicollect.strings.inputs_without_answers')))) {
                // Check the answer data exists for this input
                if (!isset($entryAnswers[$inputRef])) {
                    // If it doesn't, there we have a missing input answer in the upload
                    $this->errors[$inputRef] = ['ec5_80'];
                    return;
                }

                // Answer data, if it exists, empty string if it doesn't
                $answerData = $entryAnswers[$inputRef];

                // Validate each main answer
                $this->validateAnswer($project, $entryStructure, $answerData, $inputRef);
            }

            // If the input type is a group, validate each group answer
            if ($inputType === config('epicollect.strings.inputs_type.group')) {

                $groupInputs = $projectExtra->getGroupInputs($entryStructure->getFormRef(), $inputRef);
                // Loop each group input from the extra structure and validate each answer
                foreach ($groupInputs as $groupInputRef) {

                    $groupInputType = $projectExtra->getInputDetail($groupInputRef, 'type');
                    if (!in_array($groupInputType, array_keys(config('epicollect.strings.inputs_without_answers')))) {

                        // Check the answer data exists for this input
                        if (!isset($entryAnswers[$groupInputRef])) {
                            // If it doesn't, there we have a missing input answer in the upload
                            $this->errors[$groupInputRef] = ['ec5_80'];
                            return;
                        }
                        $answerData = $entryAnswers[$groupInputRef];
                        $this->validateAnswer($project, $entryStructure, $answerData, $groupInputRef);
                    }
                }
            }

            // Return if the answer had errors
            if ($this->errors) {
                return;
            }
        }
    }

    /**
     * @param ProjectDTO $project
     * @param EntryStructureDTO $entryStructure
     * @param $answerData
     * @param $inputRef
     */
    private function validateAnswer(ProjectDTO $project, EntryStructureDTO $entryStructure, $answerData, $inputRef): void
    {
        $projectExtra = $project->getProjectExtra();

        // Check this input exists
        if (!$projectExtra->inputExists($inputRef)) {
            $this->errors['upload'] = ['ec5_84'];
        }

        // Validate the answer
        $this->ruleAnswers->validate($answerData);
        if ($this->ruleAnswers->hasErrors()) {
            $this->errors = $this->ruleAnswers->errors();
            return;
        }
        // Do additional checks
        $this->ruleAnswers->additionalChecks($project, $entryStructure, $answerData, $inputRef);
        if ($this->ruleAnswers->hasErrors()) {
            $this->errors = $this->ruleAnswers->errors();
        }
    }

    /**
     * @param EntryStructureDTO $entryStructure
     * @param $requestedProjectId
     * @return bool
     */
    protected function checkCanEdit(EntryStructureDTO $entryStructure, $requestedProjectId): bool
    {
        $entry = $entryStructure->getEntry();
        // Check if we already have this UUID in the database for this project
        $uuid = $entry['entry_uuid'];

        //get entry or branch entry from the database
        if ($entryStructure->isBranch()) {
            $dbEntry = BranchEntry::where('uuid', '=', $uuid)->first();
        } else {
            $dbEntry = Entry::where('uuid', '=', $uuid)->first();
        }
        // EDIT
        if ($dbEntry) {
            //if the project ID does not match (bulk uploads between cloned projects for example, where the source uuid is provided by mistake), bail out
            if ($dbEntry->project_id !== $requestedProjectId) {
                return false;
            }

            // If the user can't edit this entry, return false
            if (!$entryStructure->canEdit($dbEntry)) {
                return false;
            }

            // Add existing entry to structure
            $entryStructure->addExistingEntry($dbEntry);
        }
        return true;
    }
}
