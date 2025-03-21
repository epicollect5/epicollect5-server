<?php

namespace ec5\Http\Validation\Entries\Upload\InputRules;

use ec5\DTO\ProjectDTO;

class RuleTextareaInput extends RuleInputBase
{
    /**
     * @param $inputDetails
     * @param string|array $answer
     * @param ProjectDTO $project
     */
    public function setRules($inputDetails, $answer, ProjectDTO $project): void
    {
        // Set rules based on the input details
        // Source will be the input ref

        // If we have a regex set, add to rules
        if ($inputDetails['regex']) {
            $this->rules[$inputDetails['ref']][] = 'regex:' . '/' . $inputDetails['regex'] . '/';
        }

        // Set remaining rules in the parent class
        parent::setRules($inputDetails, $answer, $project);

    }
}
