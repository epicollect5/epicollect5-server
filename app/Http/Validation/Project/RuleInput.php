<?php

namespace ec5\Http\Validation\Project;

use ec5\Http\Validation\ValidationBase;

class RuleInput extends ValidationBase
{
    protected $rules = [
        'ref' => 'required',
        'question' => 'required', // Question length checked in additionalChecks()
        'is_title' => 'boolean',
        'is_required' => 'boolean',
        'regex' => '',
        'default' => '',
        'verify' => 'boolean',
        'max' => 'nullable|numeric|ec5_greater_than_field:min',
        'min' => 'nullable|numeric',
        'set_to_current_datetime' => 'boolean',
        'possible_answers' => 'array',
        'jumps' => 'array',
        'branch' => 'array',
        'group' => 'array'
    ];

    /**
     * RuleInput constructor.
     */
    public function __construct()
    {
        $this->rules['type'] = 'required|in:' . implode(',', array_keys(config('epicollect.strings.inputs_type')));
        $this->rules['datetime_format'] = 'nullable|in:' . implode(',', array_keys(config('epicollect.strings.datetime_format')));
    }

    //Add any additional rules to validate against
    public function addAdditionalRules(bool $isBranchInput): void
    {
        if ($isBranchInput) {
            $this->rules['uniqueness'] = 'required|in:none,form';
        } else {
            $this->rules['uniqueness'] = 'required|in:none,form,hierarchy';
        }
    }

    public function additionalChecks(string $parentRef): void
    {
        // Check has parent ref in its ref
        $this->isValidRef($parentRef);

        $inputType = $this->data['type'];

        // Set the question length limit
        switch ($inputType) {
            case config('epicollect.strings.inputs_type.readme'):
                // If we have a type 'readme', the limit is higher
                $questionLengthLimit = config('epicollect.limits.readme_question_limit');
                // Decode then strip the html tags
                $question = strip_tags(html_entity_decode($this->data['question']));
                break;
            default:
                $question = $this->data['question'];
                $questionLengthLimit = config('epicollect.limits.question_limit');
        }

        // Check the length
        if (mb_strlen($question, 'UTF-8') > $questionLengthLimit) {
            $this->addAdditionalError($this->data['ref'], 'ec5_244');
            return;
        }

        //imp: this method call is dynamic
        $methodName = 'additionalRule' . ucfirst($inputType);
        if (method_exists($this, $methodName)) {
            /** @see additionalRulePhoto() */
            /** @see additionalRuleAudio() */
            /** @see additionalRuleVideo() */
            /** @see additionalRuleDate() */
            /** @see additionalRuleTime() */
            /** @see additionalRuleRadio() */
            /** @see additionalRuleDropdown() */
            /** @see additionalRuleCheckbox() */
            /** @see additionalRuleSearchsingle() */
            /** @see additionalRuleSearchMultiple() */
            /** @see additionalRuleInteger() */
            /** @see additionalRuleDecimal() */
            call_user_func([$this, $methodName]);
        }

        // Check jumps
        // todo check that no inputs in a group have jumps set
        $this->checkJumps();

    }

    private function additionalRulePhoto(): void
    {
        $this->validateMediaType();
    }

    private function additionalRuleAudio(): void
    {
        $this->validateMediaType();
    }

    private function additionalRuleVideo(): void
    {
        $this->validateMediaType();
    }

    /**
     */
    private function additionalRuleDate(): void
    {
        $this->validateDateTimeFormat();
    }

    private function additionalRuleTime(): void
    {
        $this->validateDateTimeFormat();
    }

    private function additionalRuleDropdown(): void
    {
        $this->validatePossibleAnswersCount('ec5_338');
        $this->validatePossibleAnswers();
    }

    private function additionalRuleRadio(): void
    {
        $this->validatePossibleAnswersCount('ec5_337');
        $this->validatePossibleAnswers();
    }

    private function additionalRuleCheckbox(): void
    {
        $this->validatePossibleAnswersCount('ec5_336');
        $this->validatePossibleAnswers();
    }

    private function additionalRuleSearchsingle(): void
    {
        $this->validateSearchType();
    }

    private function additionalRuleSearchmultiple(): void
    {
        $this->validateSearchType();
    }

    private function additionalRuleInteger(): void
    {
        // Check default answer is valid, ie empty string, string zero or an integer
        if ($this->data['default'] !== '' && $this->data['default'] !== '0' && filter_var(
            $this->data['default'],
            FILTER_VALIDATE_INT
        ) === false
        ) {
            $this->addAdditionalError($this->data['ref'], 'ec5_339');
        }
        // If not empty default, min and max
        $this->ifNotEmptyDefaultMinAndMax();
    }

    private function additionalRuleDecimal(): void
    {
        // Check default answer is valid, ie empty string or a number (int or decimal)
        if ($this->data['default'] !== '' && !is_numeric($this->data['default'])) {
            $this->addAdditionalError($this->data['ref'], 'ec5_339');
        }
        // If not empty default, min and max
        $this->ifNotEmptyDefaultMinAndMax();
    }

    private function checkJumps(): void
    {
        $jumps = $this->data['jumps'];
        $jump_keys = array_keys(config('epicollect.strings.jump_keys'));

        // Loop jumps and check no values are empty
        foreach ($jumps as $jump) {

            // Check that certain types only have certain values for 'when' etc i.e. text input always has 'ALL'
            switch ($this->data['type']) {
                case config('epicollect.strings.inputs_type.checkbox'):
                case config('epicollect.strings.inputs_type.radio'):
                case config('epicollect.strings.inputs_type.dropdown'):
                case config('epicollect.strings.inputs_type.searchsingle'):
                case config('epicollect.strings.inputs_type.searchmultiple'):
                    // checkbox/radio/dropdown allowed all types of jumps

                    // If not 'ALL' or 'NO_ANSWER_GIVEN'
                    if (($jump['when'] !== config('epicollect.strings.jumps.ALL') && $jump['when'] !== config('epicollect.strings.jumps.NO_ANSWER_GIVEN'))) {
                        // Check answer ref is valid in the jump
                        $match = false;
                        // Search for a match
                        foreach ($this->data['possible_answers'] as $possibleAnswerDetails) {
                            if ($jump['answer_ref'] == $possibleAnswerDetails['answer_ref']) {
                                $match = true;
                            }
                        }
                        // If no match found, error
                        if (!$match) {
                            $this->addAdditionalError($this->data['ref'], 'ec5_265');
                        }
                    }

                    break;
                default:
                    if ($jump['when'] !== config('epicollect.strings.jumps.ALL')) {
                        $this->addAdditionalError($this->data['ref'], 'ec5_207');
                    }
            }

            // If we have an empty 'to' - error
            // If we have an empty 'when' - error
            // If we have an empty 'answer_ref' and 'when' is not 'ALL' or 'NO_ANSWER_GIVEN' - error
            if (empty($jump['to']) ||
                empty($jump['when']) ||
                (empty($jump['answer_ref']) &&
                    ($jump['when'] !== config('epicollect.strings.jumps.ALL') && $jump['when'] !== config('epicollect.strings.jumps.NO_ANSWER_GIVEN')))
            ) {
                $this->addAdditionalError($this->data['ref'], 'ec5_207');
            }


            //check jump object does not contain extra keys
            foreach ($jump as $key => $value) {
                if (!in_array($key, $jump_keys, true)) {
                    $this->addAdditionalError($this->data['ref'], 'ec5_207');
                }
            }

        }
    }

    private function ifNotEmptyDefaultMinAndMax(): void
    {
        if ($this->data['default'] !== '' && $this->data['min'] !== '' && $this->data['max'] !== '') {
            // Check min/max according to default
            if ($this->data['default'] > $this->data['max'] || $this->data['default'] < $this->data['min']) {
                $this->addAdditionalError($this->data['ref'], 'ec5_28');
            }
        }
    }

    private function validateSearchType(): void
    {
        if (count($this->data['possible_answers']) == 0) {
            $this->addAdditionalError($this->data['ref'], 'ec5_342');
            $this->addAdditionalError('question', $this->data['question']);
        }

        if (count($this->data['possible_answers']) > config('epicollect.limits.possible_answers_search_limit')) {
            $this->addAdditionalError($this->data['ref'], 'ec5_340');
            $this->addAdditionalError('question', $this->data['question']);
        }

        $this->validatePossibleAnswers();
    }

    private function validatePossibleAnswersCount($code): void
    {
        if (count($this->data['possible_answers']) == 0) {
            $this->addAdditionalError($this->data['ref'], $code);
            $this->addAdditionalError('question', $this->data['question']);
        }

        if (count($this->data['possible_answers']) > config('epicollect.limits.possible_answers_limit')) {
            $this->addAdditionalError($this->data['ref'], 'ec5_340');
            $this->addAdditionalError('question', $this->data['question']);
        }
    }

    private function validatePossibleAnswers(): void
    {
        $match = false;

        foreach ($this->data['possible_answers'] as $value) {
            if ($value['answer_ref'] == $this->data['default']) {
                $match = true;
            }

            //validate possible answers length
            if (mb_strlen($value['answer'], 'UTF-8') > config('epicollect.limits.possible_answers_length_limit')) {
                $this->addAdditionalError($this->data['ref'], 'ec5_341');
                $this->addAdditionalError('question', $this->data['question']);
            }

            //validate possible answer 'answer_ref' length
            if (strlen($value['answer_ref']) !== config('epicollect.limits.possible_answer_ref_length_limit')) {
                $this->addAdditionalError($this->data['ref'], 'ec5_355');
                $this->addAdditionalError('question', $this->data['question']);
            }
        }

        // Check default answer is valid (empty or one of the possible answers)
        if ($this->data['default'] !== '' && !$match) {
            $this->addAdditionalError($this->data['ref'], 'ec5_339');
            $this->addAdditionalError('question', $this->data['question']);
        }
    }

    private function validateMediaType(): void
    {
        if (count($this->data['possible_answers']) > 0) {
            $this->addAdditionalError($this->data['ref'], 'ec5_398');
            $this->addAdditionalError('question', $this->data['question']);
        }
    }

    private function validateDateTimeFormat(): void
    {
        if ($this->data['datetime_format'] == '') {
            $this->addAdditionalError($this->data['ref'], 'ec5_79');
            $this->addAdditionalError('question', $this->data['question']);
        }
    }
}
