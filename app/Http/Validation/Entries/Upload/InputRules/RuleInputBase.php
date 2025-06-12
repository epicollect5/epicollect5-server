<?php

namespace ec5\Http\Validation\Entries\Upload\InputRules;

use ec5\DTO\EntryStructureDTO;
use ec5\DTO\ProjectDTO;
use ec5\Http\Validation\ValidationBase;

class RuleInputBase extends ValidationBase
{
    /*
   |--------------------------------------------------------------------------
   | RuleInputBase class
   |--------------------------------------------------------------------------
   |
   | This class contains common functions which can be used and overridden
   | by the specific input type validator classes.
   |
   */

    /**
     * @var array
     */
    protected array $rules = [];

    /**
     * Set common messages used by all input type rules
     * @var array
     */
    protected array $messages = [
        'regex' => 'ec5_29',
        'array' => 'ec5_29',
        'date' => 'ec5_79',
        'in' => 'ec5_29',
        'max' => 'ec5_214',
        'ec5_integer' => 'ec5_27',
        'ec5_max_length' => 'ec5_214'
    ];

    /**
     * Set common rules used by all input type rules
     *
     */
    public function setRules(array $inputDetails, string|array|null $answer, ProjectDTO $project): void
    {
        // Check the max length of this input answer has not been exceeded
        // If a limit exists for the input type
        if (config('epicollect.limits.entry_answer_limits.' . $inputDetails['type']) !== null) {
            $this->rules[$inputDetails['ref']][] = 'ec5_max_length:' . config('epicollect.limits.entry_answer_limits.' . $inputDetails['type']);
        }
    }

    public function additionalChecks(array $inputDetails, string|array|null $answer, ProjectDTO $project, EntryStructureDTO $entryStructure): array|string|null
    {
        return $answer;
    }

    protected function setMinMaxRule(array $inputDetails): void
    {
        if ($inputDetails['min'] != null) {
            $this->rules[$inputDetails['ref']][] = 'min:' . $inputDetails['min'];
        }
        if ($inputDetails['max'] != null) {
            $this->rules[$inputDetails['ref']][] = 'max:' . $inputDetails['max'];
        }

        // If we have a regex set, add to rules
        if ($inputDetails['regex'] != null) {
            $this->rules[$inputDetails['ref']][] = 'regex:' . '/' . $inputDetails['regex'] . '/';
        }
    }
}
