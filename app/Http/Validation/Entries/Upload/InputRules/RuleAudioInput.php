<?php

namespace ec5\Http\Validation\Entries\Upload\InputRules;

use ec5\DTO\ProjectDTO;

class RuleAudioInput extends RuleInputBase
{
    /**
     * @param $inputDetails
     * @param string|array $answer
     * @param ProjectDTO $project
     */
    public function setRules($inputDetails, $answer, ProjectDTO $project)
    {
        // Override message for regex
        $this->messages['regex'] = 'ec5_81';

        // Set rules based on the input details
        // Source will be the input ref

        // Check audio file name is in correct format
        $this->rules[$inputDetails['ref']] = ['regex:/^.*\.(mp4|wav)$/i'];

        // Set remaining rules in the parent class
        parent::setRules($inputDetails, $answer, $project);

    }

}
