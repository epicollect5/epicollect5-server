<?php

namespace ec5\Http\Validation\Entries\Upload\InputRules;

use ec5\Http\Validation\ValidationBase;
use ec5\Models\Projects\Project;
use ec5\Models\Entries\EntryStructure;

use Config;

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
     * @param Project $project
     */
    public function setRules($inputDetails, $answer, Project $project)
    {
        // Check the max length of this input answer has not been exceeded
        // If a limit exists for the input type
        if (Config::get('ec5Limits.entry_answer_limits.' . $inputDetails['type']) !== null) {
            $this->rules[$inputDetails['ref']][] = 'ec5_max_length:' . Config::get('ec5Limits.entry_answer_limits.' . $inputDetails['type']);
        }

    }

    /**
     * @param $inputDetails
     * @param $answer
     * @param Project $project
     * @param EntryStructure $entryStructure
     * @return mixed
     */
    public function additionalChecks($inputDetails, $answer, Project $project, EntryStructure $entryStructure)
    {
        return $answer;
    }

}
