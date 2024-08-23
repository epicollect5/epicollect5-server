<?php

namespace ec5\Http\Validation\Entries\Upload\InputRules;

use ec5\DTO\ProjectDTO;

class RuleGroupInput extends RuleInputBase
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

    }

}
