<?php

namespace ec5\Http\Validation\Entries\Upload\InputRules;

use ec5\Models\Projects\Project;

class RulePhoneInput extends RuleInputBase
{
    /**
     * @param $inputDetails
     * @param string|array $answer
     * @param Project $project
     */
    public function setRules($inputDetails, $answer, Project $project)
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
