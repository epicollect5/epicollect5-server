<?php

namespace ec5\Http\Validation\Entries\Upload;

use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleAudioInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleBranchInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleCheckboxInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleDateInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleDecimalInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleDropdownInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleGroupInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleIntegerInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleLocationInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RulePhoneInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RulePhotoInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleRadioInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleSearchMultipleInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleSearchSingleInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleTextareaInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleTextInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleTimeInput;
use ec5\Http\Validation\Entries\Upload\InputRules\RuleVideoInput;
use ec5\Http\Validation\ValidationBase;
use ec5\Services\Entries\EntriesUniquenessService;
use Exception;
use Log;

class RuleAnswers extends ValidationBase
{
    /**
     * @var array
     */
    protected $rules = [
        'was_jumped' => 'required|boolean',
        'answer' => 'present'
    ];

    protected $messages = [
        'required' => 'ec5_21',
        'boolean' => 'ec5_29',
        'present' => 'ec5_21'
    ];

    /**
     * Class variable, input ref for the particular answer
     * used throughout helper validation functions in this class
     *
     * @var string
     */
    protected $inputRef = '';
    protected $ruleIntegerInput,
        $ruleDecimalInput,
        $ruleRadioInput,
        $ruleTextInput,
        $ruleTextareaInput,
        $ruleDateInput,
        $ruleTimeInput,
        $ruleLocationInput,
        $ruleCheckboxInput,
        $ruleDropdownInput,
        $rulePhotoInput,
        $ruleVideoInput,
        $ruleAudioInput,
        $ruleBranchInput,
        $ruleGroupInput,
        $rulePhoneInput,
        $ruleSearchsingleInput,
        $ruleSearchmultipleInput;

    /**
     * @param RuleIntegerInput $ruleIntegerInput
     * @param RuleDecimalInput $ruleDecimalInput
     * @param RuleRadioInput $ruleRadioInput
     * @param RuleTextInput $ruleTextInput
     * @param RuleTextareaInput $ruleTextareaInput
     * @param RuleDateInput $ruleDateInput
     * @param RuleTimeInput $ruleTimeInput
     * @param RuleLocationInput $ruleLocationInput
     * @param RuleCheckboxInput $ruleCheckboxInput
     * @param RulePhotoInput $rulePhotoInput
     * @param RuleVideoInput $ruleVideoInput
     * @param RuleAudioInput $ruleAudioInput
     * @param RuleBranchInput $ruleBranchInput
     * @param RuleGroupInput $ruleGroupInput
     * @param RuleDropdownInput $ruleDropdownInput
     * @param RulePhoneInput $rulePhoneInput
     * @param RuleSearchSingleInput $ruleSearchSingleInput
     * @param RuleSearchMultipleInput $ruleSearchMultipleInput
     */
    public function __construct(
        RuleIntegerInput        $ruleIntegerInput,
        RuleDecimalInput        $ruleDecimalInput,
        RuleRadioInput          $ruleRadioInput,
        RuleTextInput           $ruleTextInput,
        RuleTextareaInput       $ruleTextareaInput,
        RuleDateInput           $ruleDateInput,
        RuleTimeInput           $ruleTimeInput,
        RuleLocationInput       $ruleLocationInput,
        RuleCheckboxInput       $ruleCheckboxInput,
        RulePhotoInput          $rulePhotoInput,
        RuleVideoInput          $ruleVideoInput,
        RuleAudioInput          $ruleAudioInput,
        RuleBranchInput         $ruleBranchInput,
        RuleGroupInput          $ruleGroupInput,
        RuleDropdownInput       $ruleDropdownInput,
        RulePhoneInput          $rulePhoneInput,
        RuleSearchSingleInput   $ruleSearchSingleInput,
        RuleSearchMultipleInput $ruleSearchMultipleInput
    )
    {
        $this->ruleIntegerInput = $ruleIntegerInput;
        $this->ruleDecimalInput = $ruleDecimalInput;
        $this->ruleRadioInput = $ruleRadioInput;
        $this->ruleTextInput = $ruleTextInput;
        $this->ruleTextareaInput = $ruleTextareaInput;
        $this->ruleDateInput = $ruleDateInput;
        $this->ruleTimeInput = $ruleTimeInput;
        $this->ruleLocationInput = $ruleLocationInput;
        $this->ruleCheckboxInput = $ruleCheckboxInput;
        $this->rulePhotoInput = $rulePhotoInput;
        $this->ruleVideoInput = $ruleVideoInput;
        $this->ruleAudioInput = $ruleAudioInput;
        $this->ruleBranchInput = $ruleBranchInput;
        $this->ruleGroupInput = $ruleGroupInput;
        $this->ruleDropdownInput = $ruleDropdownInput;
        $this->rulePhoneInput = $rulePhoneInput;
        $this->ruleSearchsingleInput = $ruleSearchSingleInput;
        $this->ruleSearchmultipleInput = $ruleSearchMultipleInput;
    }

    /**
     * Function to validate the answers in an entry
     * against the rules set in the input structures
     * We will add answers to the entry array that will be inserted into the database
     *
     * @param ProjectDTO $project
     * @param EntryStructureDTO $entryStructure
     * @param $answerData
     * @param $inputRef
     */
    public function additionalChecks(ProjectDTO $project, EntryStructureDTO $entryStructure, $answerData, $inputRef)
    {
        $projectExtra = $project->getProjectExtra();

        // Set 'this' input ref
        $this->inputRef = $inputRef;
        // Input
        $input = $projectExtra->getInputData($this->inputRef);

        $answer = '';

        // If the answer was jumped
        if ($answerData['was_jumped']) {
            // Jumped answer here will be set empty (just in case answers incorrectly come through)
        } else {
            // If the answer wasn't jumped, validate the answer value
            // Check the answer is valid, against the rules set in the type of the input

            // Required check
            if (!$this->checkRequired($input['is_required'], $answerData['answer'])) {
                return;
            }

            // Uniqueness check
            if (!empty($input['uniqueness']) && $input['uniqueness'] != 'none' && $answerData['answer'] != '') {
                if (!$this->isUnique($entryStructure, $input['uniqueness'], $answerData['answer'], $input['type'], $input['datetime_format'])) {
                    $this->errors[$this->inputRef] = ['ec5_22'];
                    return;
                }
            }

            try {
                // Retrieve the answer returned from the additional checks
                $answer = $this->validateAnswer($input, $answerData['answer'], $project, $entryStructure);
            } catch (Exception $e) {
                Log::error('error', ['exception' => $e->getMessage()]);
                Log::error(
                    'Exception thrown, something wrong with input answer' . $inputRef,
                    [
                        'exception' => json_decode(json_encode($e))
                    ]
                );
                $this->errors[$this->inputRef] = ['ec5_76'];
                return;
            }
        }

        // Set the $answerData['answer'] as $answer
        $answerData['answer'] = $answer;

        // Add validated answer to the entry structure
        //imp: done here because we have direct access to the input
        $entryStructure->addAnswerToEntry($input, $answerData);
    }

    /* HELPER FUNCTIONS */

    /**
     * Check answers are valid for each input, returning valid answer
     * for insertion to answers/branch_answers table
     *
     * @param $input
     * @param $answer
     * @param ProjectDTO $project
     * @param EntryStructureDTO|null $entryStructure
     * @return mixed
     */
    public function validateAnswer($input, $answer, ProjectDTO $project, EntryStructureDTO $entryStructure = null)
    {
        // todo use reflection here instead? or switch?
        // Construct dynamic variable name, base on input type
        $variableName = 'rule' . ucfirst($input['type']) . 'Input';

        // If the input type rule within this class, validate
        if (property_exists($this, $variableName)) {

            // Set the answer rules
            $this->$variableName->setRules($input, $answer, $project);

            // Validate the answer
            $data = [$input['ref'] => $answer];
            $this->$variableName->validate($data);
            if ($this->$variableName->hasErrors()) {
                $this->errors = $this->$variableName->errors();
            }

            if ($entryStructure != null) {
                // Additional checks on the answer
                $answer = $this->$variableName->additionalChecks($input, $answer, $project, $entryStructure);
                if ($this->$variableName->hasErrors()) {
                    $this->errors = $this->$variableName->errors();
                }
            }
        }

        return $answer;
    }

    /**
     * Check whether answer is required and not empty
     *
     * @param $isRequired
     * @param $answer
     * @return bool
     */
    private function checkRequired($isRequired, $answer): bool
    {
        // If the answer is 'required', check it's not empty
        // empty means no null, not an empty string and not an empty array
        if ($isRequired && ($answer === null || $answer === '' || $answer === [])) {
            $this->errors[$this->inputRef] = ['ec5_21'];
            return false;
        }
        return true;
    }

    /**
     * Check the uniqueness of the answer
     *
     * @param EntryStructureDTO $entryStructure
     * @param $uniquenessType
     * @param $answer
     * @param $inputType
     * @param $inputDatetimeFormat
     * @return bool|null
     */
    private function isUnique(EntryStructureDTO $entryStructure, $uniquenessType, $answer, $inputType, $inputDatetimeFormat): ?bool
    {
        $entryService = new EntriesUniquenessService();
        return $entryService->isUnique($entryStructure, $uniquenessType, $this->inputRef, $answer, $inputType, $inputDatetimeFormat);
    }
}
