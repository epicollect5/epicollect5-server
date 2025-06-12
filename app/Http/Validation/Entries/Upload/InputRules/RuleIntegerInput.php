<?php

namespace ec5\Http\Validation\Entries\Upload\InputRules;

use ec5\DTO\ProjectDTO;

class RuleIntegerInput extends RuleInputBase
{
    public function setRules($inputDetails, $answer, ProjectDTO $project): void
    {
        // Override certain messages
        $this->messages['min'] = 'ec5_215';
        $this->messages['max'] = 'ec5_216';
        $this->messages['regex'] = 'ec5_27';

        // Set rules based on the input details
        // Source will be the input ref

        $this->rules[$inputDetails['ref']] = ['ec5_integer', 'numeric'];

        // If we have a min and max set, add to rules
        $this->setMinMaxRule($inputDetails);
    }



}
