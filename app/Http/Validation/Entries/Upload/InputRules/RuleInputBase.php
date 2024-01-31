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
    protected $rules = [];

    /**
     * Set common messages used by all input type rules
     * @var array
     */
    protected $messages = [
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
     * @param $inputDetails
     * @param string|array $answer
     * @param ProjectDTO $project
     */
    public function setRules($inputDetails, $answer, ProjectDTO $project)
    {
        // Check the max length of this input answer has not been exceeded
        // If a limit exists for the input type
        if (config('epicollect.limits.entry_answer_limits.' . $inputDetails['type']) !== null) {
            $this->rules[$inputDetails['ref']][] = 'ec5_max_length:' . config('epicollect.limits.entry_answer_limits.' . $inputDetails['type']);
        }

    }

    /**
     * @param $inputDetails
     * @param $answer
     * @param ProjectDTO $project
     * @param EntryStructureDTO $entryStructure
     * @return mixed
     */
    public function additionalChecks($inputDetails, $answer, ProjectDTO $project, EntryStructureDTO $entryStructure)
    {
        return $answer;
    }

}
