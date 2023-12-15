<?php

namespace ec5\Http\Validation\Entries\Upload;

use ec5\Http\Validation\ValidationBase;
use ec5\Http\Validation\Entries\Upload\RuleAnswers as AnswerValidator;

use ec5\Models\Projects\Project;
use ec5\Repositories\QueryBuilder\Entry\Upload\Search\SearchRepository;

use ec5\Models\Entries\EntryStructure;

use Config;

abstract class EntryValidationBase extends ValidationBase
{

    /*
    |--------------------------------------------------------------------------
    | EntryHandler
    |--------------------------------------------------------------------------
    |
    | This class contains common functions for entry/branch_entry uploads
    |
    */

    /**
     * @var AnswerValidator
     */
    protected $answerValidator;

    /**
     * @var
     */
    protected $searchRepository;

    /**
     * @param SearchRepository $searchRepository
     * @param AnswerValidator $answerValidator
     */
    public function __construct(SearchRepository $searchRepository, AnswerValidator $answerValidator)
    {
        $this->searchRepository = $searchRepository;
        $this->answerValidator = $answerValidator;
    }

    /**
     * Function for additional checks
     *
     * @param Project $project
     * @param EntryStructure $entryStructure
     */
    public abstract function additionalChecks(Project $project, EntryStructure $entryStructure);

    /**
     * @param Project $project
     * @param EntryStructure $entryStructure
     * @param $inputs - may be form or branch entry inputs
     */
    protected function validateAnswers(Project $project, EntryStructure $entryStructure, $inputs)
    {

        $projectExtra = $project->getProjectExtra();
        $entryAnswers = $entryStructure->getAnswers();

        // Loop round the form inputs and validate the relevant answer
        foreach ($inputs as $inputRef) {

            $inputType = $projectExtra->getInputDetail($inputRef, 'type');

            // If the input type requires an answer
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

            // If input type is a group, validate each group answer
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
     * @param Project $project
     * @param EntryStructure $entryStructure
     * @param $answerData
     * @param $inputRef
     */
    private function validateAnswer(Project $project, EntryStructure $entryStructure, $answerData, $inputRef)
    {

        $projectExtra = $project->getProjectExtra();
        $input = $projectExtra->getInputData($inputRef);

        // Check this input exists
        if (count($input) == 0) {
            // Input doesn't exist
            $this->errors['upload'] = ['ec5_84'];
            return;
        }

        // Validate the answer
        $this->answerValidator->validate($answerData);
        if ($this->answerValidator->hasErrors()) {
            $this->errors = $this->answerValidator->errors();
            return;
        }
        // Do additional checks
        $this->answerValidator->additionalChecks($project, $entryStructure, $answerData, $inputRef, $this->searchRepository);
        if ($this->answerValidator->hasErrors()) {
            $this->errors = $this->answerValidator->errors();
            return;
        }
    }

    /**
     * @param EntryStructure $entryStructure
     * @param $requestedProjectId
     * @return bool
     */
    protected function checkCanEdit(EntryStructure $entryStructure, $requestedProjectId)
    {
        $entry = $entryStructure->getEntry();

        // Check if we already have this UUID in the database for this projectt
        $uuid = $entry['entry_uuid'];

        //the moron who wrote this never considered we might have more than one parameter to pass, go figure :/
        $dbEntry = $this->searchRepository->where('uuid', '=', $uuid);

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
