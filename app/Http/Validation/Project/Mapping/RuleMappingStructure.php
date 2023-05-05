<?php

namespace ec5\Http\Validation\Project\Mapping;

use ec5\Http\Validation\ValidationBase;
use ec5\Http\Validation\Project\Mapping\RuleMappingInput as MappingInputValidator;
use ec5\Http\Validation\Project\Mapping\RuleMappingPossibleAnswer as MappingPossibleAnswerValidator;

use ec5\Models\Projects\Project;
use ec5\Models\Projects\ProjectExtra;
use Config;

class RuleMappingStructure extends ValidationBase
{
    protected $rules = [
        'name' => 'required|string|min:3|max:20|regex:/^[A-Za-z0-9 \-\_]+$/',
        'forms' => 'required|array',
        'is_default' => 'required|boolean',
        'map_index' => 'required|integer'
    ];

    /**
     * @var RuleMappingInput
     */
    protected $mappingInputValidator;

    /**
     * @var RuleMappingPossibleAnswer
     */
    protected $mappingPossibleAnswerValidator;

    /**
     * RuleMappingStructure constructor.
     * @param RuleMappingInput $mappingInputValidator
     * @param RuleMappingPossibleAnswer $mappingPossibleAnswerValidator
     */
    public function __construct(MappingInputValidator $mappingInputValidator, MappingPossibleAnswerValidator $mappingPossibleAnswerValidator)
    {
        $this->mappingInputValidator = $mappingInputValidator;
        $this->mappingPossibleAnswerValidator = $mappingPossibleAnswerValidator;
    }

    /**
     * @param Project $project
     * @param $mapping
     * @return bool
     */
    public function additionalChecks(Project $project, $mapping): bool
    {
        $projectExtra = $project->getProjectExtra();

        // Loop forms and get the auto generated map for each
        foreach ($mapping['forms'] as $formRef => $inputs) {

            // Reset the list of unique input mappings name keys (reset for each form)
            $this->mappingInputValidator->resetUniqueMappedNames();

            // Check each form
            if (!$this->checkForm($projectExtra, $formRef)) {
                return false;
            }

            // Check inputs
            if (!$this->checkInputs($projectExtra, $formRef, $inputs)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check a form mapping
     *
     * @param ProjectExtra $projectExtra
     * @param $formRef
     * @return bool
     */
    private function checkForm(ProjectExtra $projectExtra, $formRef): bool
    {
        // Check the form ref exists
        if (count($projectExtra->getFormDetails($formRef)) == 0) {
            $this->addAdditionalError($formRef, 'ec5_15');
            return false;
        }

        return true;
    }

    /**
     * Check all inputs
     *
     * @param ProjectExtra $projectExtra
     * @param $formRef
     * @param $mappedInputs
     * @return bool
     */
    public function checkInputs(ProjectExtra $projectExtra, $formRef, $mappedInputs): bool
    {
        $excludedTypes = Config::get('ec5Enums.exclude_from_mapping');

        foreach ($mappedInputs as $inputRef => $mappedInput) {

            // Check this input exists
            if (count($projectExtra->getInput($inputRef)) == 0) {
                $this->addAdditionalError($inputRef, 'ec5_84');
                return false;
            }

            // Get input data
            $inputData = $projectExtra->getInputData($inputRef);

            // Check this input type is not in the excluded list
            if (in_array($inputData['type'], $excludedTypes)) {
                $this->addAdditionalError($inputRef, 'ec5_29');
                return false;
            }

            if (!$this->checkInput($projectExtra, $formRef, $mappedInput, $inputData)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check an input mapping
     *
     * @param ProjectExtra $projectExtra
     * @param $formRef
     * @param $mappedInput
     * @param $inputData
     * @return bool
     */
    private function checkInput(ProjectExtra $projectExtra, $formRef, $mappedInput, $inputData): bool
    {

        // Check each input mapping against the mapping input validator
        $this->mappingInputValidator->validate($mappedInput);
        if ($this->mappingInputValidator->hasErrors()) {
            $this->errors = $this->mappingInputValidator->errors();
            return false;
        }

        // Additional checks - whether the map to name is unique or not
        $this->mappingInputValidator->checkAdditional($mappedInput['map_to'], $inputData['ref']);
        if ($this->mappingInputValidator->hasErrors()) {
            $this->errors = $this->mappingInputValidator->errors();
            return false;
        }

        // Check groups
        if (count($mappedInput['group']) > 0) {
            if (!$this->checkInputs($projectExtra, $formRef, $mappedInput['group'])) {
                return false;
            }
        }

        // Check branches
        if (count($mappedInput['branch']) > 0) {
            if (!$this->checkInputs($projectExtra, $formRef, $mappedInput['branch'])) {
                return false;
            }
        }

        // Check possible answers
        if (count($mappedInput['possible_answers']) > 0) {
            if (!$this->checkPossibleAnswers($projectExtra, $inputData['ref'], $mappedInput['possible_answers'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param ProjectExtra $projectExtra
     * @param $inputRef
     * @param $mappedPossibleAnswers
     * @return bool
     */
    public function checkPossibleAnswers(ProjectExtra $projectExtra, $inputRef, $mappedPossibleAnswers): bool
    {

        foreach ($mappedPossibleAnswers as $answerRef => $possibleAnswerMapping) {

            // Check possible answer answerRef exists
            if (!$projectExtra->possibleAnswerExists($inputRef, $answerRef)) {
                $this->addAdditionalError($answerRef, 'ec5_25');
                return false;
            }

            // Validate the mapping
            $this->mappingPossibleAnswerValidator->validate($possibleAnswerMapping);
            if ($this->mappingPossibleAnswerValidator->hasErrors()) {
                $this->addAdditionalError($answerRef, 'ec5_233');
                return false;
            }
        }

        return true;
    }
}
